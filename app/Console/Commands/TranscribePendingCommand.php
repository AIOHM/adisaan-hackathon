<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\EchoSubmission;
use App\Services\Tbwim\VoiceTranscriber;
use Illuminate\Console\Command;

/** Transcribe recorded answers that still wait for text, oldest first. */
final class TranscribePendingCommand extends Command
{
    protected $signature = 'tbwim:transcribe-pending {--limit=25} {--receipt= : One submission by receipt}';

    protected $description = 'Transcribe recorded answers that have no transcript yet';

    public function handle(VoiceTranscriber $transcriber): int
    {
        $query = EchoSubmission::query()->whereNotNull('metadata->media')->whereNull('transcript')->orderBy('created_at');
        if (is_string($receipt = $this->option('receipt')) && $receipt !== '') {
            $query->where('public_id', $receipt);
        } else {
            $query->limit(max(1, (int) $this->option('limit')));
        }
        $done = $skipped = $failed = 0;
        foreach ($query->get() as $echo) {
            $result = $transcriber->transcribe($echo);
            match ($result['status']) {
                'done' => $done++,
                'failed' => $failed++,
                default => $skipped++,
            };
            $this->line(sprintf('  %-8s %s %s', $result['status'], $echo->public_id, $result['reason'] ?? ''));
        }
        $this->info(sprintf('%d transcribed · %d skipped · %d failed', $done, $skipped, $failed));

        return self::SUCCESS;
    }
}
