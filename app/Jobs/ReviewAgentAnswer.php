<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\EchoSubmission;
use App\Services\Observatory\ReviewService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** Runs the reviewer for one agent answer; never releases on review failure. */
final class ReviewAgentAnswer implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 150;

    public int $tries = 2;

    public function __construct(public readonly string $receipt) {}

    public function handle(ReviewService $review): void
    {
        $echo = EchoSubmission::query()->where('public_id', $this->receipt)->first();
        if (! $echo || ! $echo->isAgent() || $echo->status === EchoSubmission::STATUS_QUARANTINED || $echo->reviewed_at !== null) {
            return;
        }
        $review->review($echo);
    }
}
