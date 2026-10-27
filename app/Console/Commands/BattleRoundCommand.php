<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\ReviewAgentAnswer;
use App\Models\EchoSubmission;
use App\Services\Meta\MetaClient;
use App\Services\Tbwim\Questions;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * One battle round: the same question, the same prompt, to every contender.
 * Answers are stored exactly as the agent API stores them and go through
 * the same review. A failed contender is reported, never invented.
 */
final class BattleRoundCommand extends Command
{
    protected $signature = 'tbwim:battle-round {--question= : Question id (default: the current one)} {--only= : Comma-separated model ids} {--force} {--dry-run}';

    protected $description = 'Ask every configured Meta model the current question and store the answers as agent answers';

    public function handle(MetaClient $meta, Questions $questions): int
    {
        $story = $questions->find((string) $this->option('question')) ?? $questions->current();
        $battle = (array) config('tbwim.battle', []);
        $contenders = (array) ($battle['contenders'] ?? []);
        $only = array_filter(array_map('trim', explode(',', (string) $this->option('only'))));
        if ($only !== []) {
            $contenders = array_values(array_filter($contenders, fn (array $c) => in_array($c['model'] ?? '', $only, true)));
        }
        $question = (string) $story['question'];
        $prompt = strtr((string) $battle['prompt'], [':question' => $question, ':tail' => $this->questionTail($question)]);
        $maxTokens = (int) ($battle['max_tokens'] ?? 420);
        $roundId = (string) Str::uuid();
        $answered = EchoSubmission::query()->agents()->where('episode_ref', $story['id'])->get(['metadata'])
            ->map(fn ($r) => (string) ($r->metadata['battle']['model_id'] ?? ''))->filter()->unique()->all();

        $this->info('Question: '.$question);
        $this->line('Round:    '.$roundId);
        $this->line('---');
        $this->line($prompt);
        $this->line('---');
        $asked = $skipped = $failed = 0;
        foreach ($contenders as $c) {
            $model = (string) $c['model'];
            $label = (string) ($c['label'] ?? $model);
            if (! $this->option('force') && in_array($model, $answered, true)) {
                $this->line("  skip  {$label}: already answered this question (--force to ask again)");
                $skipped++;

                continue;
            }
            if ($this->option('dry-run')) {
                $this->line("  would ask  {$label} ({$model})");
                $asked++;

                continue;
            }
            try {
                $result = $meta->chat([['role' => 'user', 'content' => $prompt]], $model, $maxTokens, 0.7);
            } catch (\Throwable $e) {
                $this->line("  fail  {$label}: ".$e->getMessage());
                $failed++;

                continue;
            }
            if ($result['text'] === '') {
                $this->line("  fail  {$label}: empty answer, not stored");
                $failed++;

                continue;
            }
            $echo = new EchoSubmission;
            $echo->body = $result['text'];
            $echo->signature = $label.' · '.($c['developer'] ?? 'Meta');
            $echo->prompt_key = Str::slug($question);
            $echo->prompt_text = $question;
            $echo->episode_ref = $story['id'];
            $echo->status = EchoSubmission::STATUS_RECEIVED;
            $echo->consent_quote = true;
            $echo->language = 'en';
            $echo->metadata = [
                'respondent_type' => 'agent', 'agent_model' => $label, 'agent_role' => 'Battle contender', 'agent_developer' => (string) ($c['developer'] ?? 'Meta'),
                'agent_source_url' => '', 'agent_identity_verified' => true, 'review_status' => 'pending', 'intent_type' => 'best_way_imagine', 'source_channel' => 'battle_round',
                'created_at_iso' => now()->toIso8601String(),
                'battle' => ['round_id' => $roundId, 'model_id' => $model, 'reported_model' => $result['model'], 'prompt' => $prompt, 'max_tokens' => $maxTokens,
                    'latency_ms' => $result['latency_ms'], 'prompt_tokens' => $result['prompt_tokens'], 'completion_tokens' => $result['completion_tokens'], 'asked_at_iso' => now()->toIso8601String()],
            ];
            $echo->save();
            ReviewAgentAnswer::dispatch($echo->public_id);
            $asked++;
            $this->line(sprintf('  ok    %s %dms %s tokens: %s', $label, $result['latency_ms'], ($result['prompt_tokens'] ?? 0) + ($result['completion_tokens'] ?? 0), Str::limit($result['text'], 70)));
        }
        $this->info(sprintf('%d %s · %d skipped · %d failed', $asked, $this->option('dry-run') ? 'would ask' : 'answered', $skipped, $failed));

        return $failed > 0 && $asked === 0 ? self::FAILURE : self::SUCCESS;
    }

    private function questionTail(string $question): string
    {
        $clean = trim(rtrim(trim($question), '?'));

        return preg_match('/^what is the best way you imagine\s+(.+)$/i', $clean, $m) === 1 ? trim($m[1]) : 'here';
    }
}
