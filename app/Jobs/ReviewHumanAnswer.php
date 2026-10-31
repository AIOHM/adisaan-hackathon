<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\EchoSubmission;
use App\Services\Tbwim\ReviewService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * The doorkeeper before the Telegram ping: runs the reviewer over one
 * human answer and only wakes the owner when the answer passes or the
 * reviewer itself is down (the owner is the backstop). Held answers —
 * off-topic, spam, test messages — stay silent and recorded.
 */
final class ReviewHumanAnswer implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 150;

    public int $tries = 2;

    public function __construct(public readonly string $receipt) {}

    public function handle(ReviewService $review): void
    {
        $echo = EchoSubmission::query()->where('public_id', $this->receipt)->first();
        if (! $echo || $echo->isAgent()
            || $echo->status === EchoSubmission::STATUS_QUARANTINED
            || ($echo->metadata['decided_by'] ?? null) !== null
            || ($echo->metadata['owner_notified_at'] ?? null) !== null) {
            return;
        }
        if ($echo->reviewed_at !== null) {
            // A pass already read it; only advice that was not a hold pings.
            if (($echo->metadata['review_status'] ?? null) !== 'held') {
                NotifyOwnerOfAnswer::dispatch($echo->public_id);
            }

            return;
        }
        if (is_array($echo->metadata['media'] ?? null) && trim((string) $echo->transcript) === '') {
            // The placeholder body says nothing; judge the words once the
            // transcription lands. After three waits the owner hears anyway.
            if ($this->attempts() >= 3) {
                NotifyOwnerOfAnswer::dispatch($echo->public_id);

                return;
            }
            $this->release(300);

            return;
        }
        $verdict = $review->review($echo);
        if ($verdict['release'] || $verdict['error'] !== null) {
            NotifyOwnerOfAnswer::dispatch($echo->public_id);
        }
    }
}
