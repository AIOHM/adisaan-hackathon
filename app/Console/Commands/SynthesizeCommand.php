<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\EchoSubmission;
use App\Models\QuestionSynthesis;
use App\Services\Tbwim\Questions;
use App\Services\Tbwim\QuestionSynthesizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/** The breathing answer: when new human voices arrived, write the next version from all of them in one call. */
final class SynthesizeCommand extends Command
{
    protected $signature = 'tbwim:synthesize {--question= : Question id (default: the current one)} {--min-voices=} {--force} {--dry-run}';

    protected $description = 'Compile the human voices on a question into a new version of the breathing answer';

    public function handle(QuestionSynthesizer $synthesizer, Questions $questions): int
    {
        $story = $questions->find((string) $this->option('question')) ?? $questions->current();
        $ref = (string) $story['id'];
        $question = (string) $story['question'];
        $minVoices = max(1, (int) ($this->option('min-voices') ?: config('tbwim.synthesis.min_voices', 5)));

        $site = EchoSubmission::query()->countable()->humans()->where('episode_ref', $ref)->where('consent_quote', true)
            ->orderBy('created_at')->orderBy('id')->get(['id', 'body', 'synthesized_at'])
            ->filter(fn (EchoSubmission $e) => mb_strlen(trim((string) $e->body)) >= 40)->values();
        $last = QuestionSynthesis::query()->forQuestion($ref)->newestFirst()->first();
        $total = $site->count();
        $new = $site->whereNull('synthesized_at')->count();
        $nextVersion = ($last?->version ?? 0) + 1;

        $this->info('Question: '.$ref);
        $this->line('  '.$question);
        $this->line(sprintf('  %d consented human voices, %d new since the last version%s', $total, $new, $last ? ', last version '.$last->version.' from '.$last->generated_at?->toDateTimeString() : ', no version yet'));
        if ($total < $minVoices) {
            $this->info(sprintf('Below the minimum (%d of %d voices). Nothing written.', $total, $minVoices));

            return self::SUCCESS;
        }
        if ($new === 0 && ! $this->option('force')) {
            $this->info('No new voice since the last version. Nothing written.');

            return self::SUCCESS;
        }
        $voices = $site->map(fn (EchoSubmission $e) => ['circle' => 'site', 'text' => (string) $e->body])->all();
        if ($this->option('dry-run')) {
            $this->line($synthesizer->buildPrompt($question, $voices, $last?->body, $nextVersion));

            return self::SUCCESS;
        }
        try {
            $result = $synthesizer->synthesize($question, $voices, $last?->body, $nextVersion);
        } catch (\Throwable $e) {
            $this->error('Synthesis failed, nothing written: '.$e->getMessage());

            return self::FAILURE;
        }
        $this->newLine();
        $this->info(sprintf('Version %d (%d words, %s, %s prompt tokens):', $nextVersion, $result['words'], $result['model'], $result['prompt_tokens'] ?? '?'));
        $this->line($result['body']);
        $this->table(['theme', 'voices'], array_map(fn (array $t) => [$t['theme'], $t['voices']], $result['themes']));

        $generatedAt = now();
        DB::transaction(function () use ($ref, $nextVersion, $result, $site, $generatedAt): void {
            QuestionSynthesis::create([
                'question_ref' => $ref, 'version' => $nextVersion, 'body' => $result['body'], 'voices_site' => $site->count(), 'voices_agents' => 0,
                'themes' => $result['themes'], 'model' => $result['model'], 'prompt_version' => $result['prompt_version'],
                'prompt_tokens' => $result['prompt_tokens'], 'completion_tokens' => $result['completion_tokens'], 'generated_at' => $generatedAt,
            ]);
            foreach ($site->values() as $i => $echo) {
                $theme = $result['voice_themes'][$i + 1] ?? null;
                if ($theme !== null) {
                    EchoSubmission::query()->whereKey($echo->id)->update(['theme' => mb_substr($theme, 0, 255), 'synthesized_at' => $generatedAt, 'status' => EchoSubmission::STATUS_SYNTHESIZED]);
                }
            }
        });
        $this->info('Written: version '.$nextVersion.'.');

        return self::SUCCESS;
    }
}
