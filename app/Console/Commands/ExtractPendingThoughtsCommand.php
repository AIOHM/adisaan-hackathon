<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\ExtractThought;
use App\Models\EchoSubmission;
use Illuminate\Console\Command;

final class ExtractPendingThoughtsCommand extends Command
{
    protected $signature = 'tbwim:extract-pending {--limit=25}';

    protected $description = 'Distil transcribed founder thoughts that have no draft yet';

    public function handle(): int
    {
        $rows = EchoSubmission::query()
            ->where('metadata->founder_capture', true)
            ->whereNotNull('transcript')
            ->whereDoesntHave('thoughts')
            ->orderBy('id')->limit(max(1, (int) $this->option('limit')))->get(['id', 'public_id']);
        foreach ($rows as $echo) {
            ExtractThought::dispatch($echo->public_id);
        }
        $this->info("Queued {$rows->count()} thought extractions.");

        return self::SUCCESS;
    }
}
