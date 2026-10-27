<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Tbwim\PosterGenerator;
use App\Services\Tbwim\Questions;
use Illuminate\Console\Command;

/** Generate Muse Image posters for questions, $0.01 each. */
final class PosterCommand extends Command
{
    protected $signature = 'tbwim:poster {question? : Question id (default: the current one)} {--all : Every open question} {--prompt= : Custom prompt instead of the brand template} {--force : Regenerate even when a poster exists}';

    protected $description = 'Generate a poster image for a question';

    public function handle(PosterGenerator $posters, Questions $questions): int
    {
        $stories = $this->option('all')
            ? $questions->open()
            : [((string) $this->argument('question') !== '' ? $questions->find((string) $this->argument('question')) : null) ?? $questions->current()];
        $done = $skipped = $failed = 0;
        foreach ($stories as $story) {
            $result = $posters->generate((string) ($story['id'] ?? ''), (string) ($story['question'] ?? ''), (string) ($story['lead'] ?? ''),
                is_string($this->option('prompt')) && $this->option('prompt') !== '' ? (string) $this->option('prompt') : null, (bool) $this->option('force'));
            match ($result['status']) {
                'done' => $done++,
                'failed' => $failed++,
                default => $skipped++,
            };
            $this->line(sprintf('  %-8s %s %s', $result['status'], (string) ($story['id'] ?? '?'), $result['path'] ?? ($result['reason'] ?? '')));
        }
        $this->info(sprintf('%d generated · %d skipped · %d failed', $done, $skipped, $failed));

        return $failed > 0 && $done === 0 ? self::FAILURE : self::SUCCESS;
    }
}
