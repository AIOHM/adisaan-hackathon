<?php

declare(strict_types=1);

namespace App\Services\Tbwim;

use App\Models\EchoSubmission;

/**
 * The room an answer waits in. Rules first (no model needed to refuse a test
 * message), the model second. The original `body` is never edited; the tidied
 * text goes to `display_body`. Agent answers are released by the reviewer;
 * human answers are released only by a person (tbwim:approve).
 */
final class ReviewService
{
    public function __construct(private readonly AnswerReviewer $reviewer, private readonly Questions $questions) {}

    /** @return array{release: bool, reason: string, note: string, score: int, error: ?string, reviewed_by: string} */
    public function review(EchoSubmission $submission, bool $dryRun = false): array
    {
        $text = trim((string) $submission->body);
        if (is_array($submission->metadata['media'] ?? null) && trim((string) $submission->transcript) !== '') {
            // A recording's placeholder body says nothing; judge the words.
            $text = trim((string) $submission->transcript);
        }
        $question = $this->questionFor($submission);
        $ruled = $this->ruleCheck($text);
        if ($ruled !== null) {
            return $this->record($submission, ['release' => false, 'reason' => $ruled['reason'], 'note' => $ruled['note'], 'corrected' => '',
                'confidence' => 100, 'on_topic' => 0, 'genuine' => 0, 'error' => null, 'reviewed_by' => 'rules'], $dryRun, $text);
        }
        $verdict = $this->reviewer->review($question, $text, $submission->isAgent());
        $verdict['reviewed_by'] = $verdict['error'] === null ? 'muse:'.($verdict['model'] ?? config('tbwim.review.model')) : 'unavailable';

        return $this->record($submission, $verdict, $dryRun, $text);
    }

    public function questionFor(EchoSubmission $submission): string
    {
        $stored = trim((string) $submission->prompt_text);
        if ($stored !== '') {
            return $stored;
        }

        return (string) ($this->questions->find($submission->episode_ref)['question'] ?? $this->questions->current()['question'] ?? '');
    }

    /** @return array{reason: string, note: string}|null */
    private function ruleCheck(string $text): ?array
    {
        $trimmed = trim($text);
        if (mb_strlen($trimmed) < (int) config('tbwim.intake.minimum_length', 10)) {
            return ['reason' => 'empty', 'note' => 'Too short to be an answer.'];
        }
        if (preg_match_all('/\pL/u', $trimmed) < 8) {
            return ['reason' => 'empty', 'note' => 'Almost no words in it.'];
        }
        if (preg_match('/^\s*(te?sting|test|testare|testez|proba|probe|check|checking|hello|hi|salut|merge|functioneaz)/iu', $trimmed) === 1) {
            return ['reason' => 'test_message', 'note' => 'Reads as someone checking that the form works.'];
        }
        if (preg_match('/^(.)\1{5,}$/u', $trimmed) === 1 || preg_match('/(asdf|qwer|zxcv|jkl;|1234){2,}/iu', $trimmed) === 1) {
            return ['reason' => 'empty', 'note' => 'Keyboard mash rather than words.'];
        }

        return null;
    }

    private function record(EchoSubmission $submission, array $verdict, bool $dryRun, string $text): array
    {
        $release = (bool) ($verdict['release'] ?? false);
        // A person's answer is never released by a machine; the reviewer's
        // verdict is recorded as advice and a human decides.
        $autoRelease = $release && $submission->isAgent();
        if (! $dryRun && $verdict['error'] === null) {
            $meta = is_array($submission->metadata) ? $submission->metadata : [];
            // A person's decision is final: re-review refreshes the advice
            // below but never touches what the person decided.
            $humanDecided = ! $submission->isAgent() && ($meta['decided_by'] ?? null) === 'human';
            if (! $humanDecided) {
                $meta['review_status'] = $autoRelease ? 'approved' : ($release ? 'recommended' : 'held');
            }
            $submission->forceFill([
                'reviewed_at' => now(),
                'review_score' => (int) ($verdict['confidence'] ?? 0),
                'review_reason' => $humanDecided ? $submission->review_reason : ($release ? null : (string) ($verdict['reason'] ?? 'held')),
                'review_meta' => [
                    'on_topic' => (int) ($verdict['on_topic'] ?? 0), 'genuine' => (int) ($verdict['genuine'] ?? 0),
                    'note' => (string) ($verdict['note'] ?? ''), 'reviewed_by' => (string) ($verdict['reviewed_by'] ?? ''),
                    'model' => $verdict['model'] ?? null, 'prompt_tokens' => $verdict['prompt_tokens'] ?? null, 'completion_tokens' => $verdict['completion_tokens'] ?? null,
                    'corrected' => $release ? (string) ($verdict['corrected'] ?: $text) : null,
                ],
                'metadata' => $meta,
                'display_body' => $autoRelease ? ($verdict['corrected'] ?: $text) : ($humanDecided ? $submission->display_body : null),
                'released_at' => $autoRelease ? now() : ($submission->isAgent() ? null : $submission->released_at),
            ])->save();
        }

        return ['release' => $release, 'reason' => (string) ($verdict['reason'] ?? ''), 'note' => (string) ($verdict['note'] ?? ''),
            'score' => (int) ($verdict['confidence'] ?? 0), 'error' => $verdict['error'] ?? null, 'reviewed_by' => (string) ($verdict['reviewed_by'] ?? '')];
    }

    /** A person releases or holds a human answer; the reviewer's corrected text is used when it exists. */
    public function decide(EchoSubmission $submission, bool $approve, ?string $note = null): void
    {
        if ($submission->isAgent()) {
            throw new \LogicException('Agent answers are released by the reviewer, not by this path.');
        }
        $memberConsent = data_get($submission->metadata, 'member_sharing.status') === 'approved'
            && data_get($submission->metadata, 'member_sharing.basis') === 'sender_selected_members_only';
        if ($approve && ((! $submission->consent_quote && ! $memberConsent) || $submission->status === EchoSubmission::STATUS_QUARANTINED)) {
            throw new \LogicException('This answer is not eligible for public release (no consent, or quarantined).');
        }
        $meta = is_array($submission->metadata) ? $submission->metadata : [];
        $meta['review_status'] = $approve ? 'approved' : 'rejected';
        $meta['decided_by'] = 'human';
        $meta['decided_at'] = now()->toIso8601String();
        if ($note !== null && $note !== '') {
            $meta['decision_note'] = $note;
        }
        $corrected = (string) ($submission->review_meta['corrected'] ?? '');
        $submission->forceFill([
            'metadata' => $meta,
            'reviewed_at' => $submission->reviewed_at ?? now(),
            'display_body' => $approve ? ($corrected !== '' ? $corrected : $submission->body) : null,
            'released_at' => $approve ? now() : null,
        ])->save();
    }
}
