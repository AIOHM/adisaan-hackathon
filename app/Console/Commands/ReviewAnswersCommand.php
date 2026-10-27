<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\EchoSubmission;
use App\Services\Observatory\ReviewService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/** The pass that reads what nobody has read yet. Releases agent answers; records advice on human ones. */
final class ReviewAnswersCommand extends Command
{
    protected $signature = 'observatory:review-answers {--limit=25} {--id= : One submission by receipt, even if reviewed} {--recheck} {--dry-run}';

    protected $description = 'Review unread answers with Muse Spark: release agent answers that pass, hold the rest, advise on human answers';

    public function handle(ReviewService $review): int
    {
        $id = trim((string) $this->option('id'));
        $queue = $id !== ''
            ? EchoSubmission::query()->where('public_id', $id)->get()
            : EchoSubmission::query()
                ->when(! $this->option('recheck'), fn ($q) => $q->awaitingReview())
                ->when((bool) $this->option('recheck'), fn ($q) => $q->where('status', '!=', EchoSubmission::STATUS_QUARANTINED))
                ->orderBy('created_at')->limit(max(1, (int) $this->option('limit')))->get();
        if ($queue->isEmpty()) {
            $this->info('Nothing waiting.');

            return self::SUCCESS;
        }
        $dryRun = (bool) $this->option('dry-run');
        $this->line(sprintf('%d to review%s.', $queue->count(), $dryRun ? ' (dry run)' : ''));
        $released = $held = $failed = 0;
        foreach ($queue as $submission) {
            $verdict = $review->review($submission, $dryRun);
            if ($verdict['error'] !== null) {
                $failed++;
            } elseif ($verdict['release']) {
                $released++;
            } else {
                $held++;
            }
            $this->line(sprintf('  %-7s %-20s %-6s %-14s %s', $verdict['release'] ? 'release' : 'hold', $verdict['reason'] ?: '-', $submission->isAgent() ? 'agent' : 'human', $verdict['reviewed_by'], Str::limit(str_replace("\n", ' ', $submission->body), 60)));
            if ($verdict['error'] !== null) {
                $this->line('          '.$verdict['error']);
            }
        }
        $this->newLine();
        $this->info(sprintf('pass %d · hold %d · could not review %d', $released, $held, $failed));

        return $failed > 0 && $released === 0 && $held === 0 ? self::FAILURE : self::SUCCESS;
    }
}
