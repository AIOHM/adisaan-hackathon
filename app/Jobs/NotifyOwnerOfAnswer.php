<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\EchoSubmission;
use App\Services\Tbwim\OwnerAlerts;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** Pings the owner about one human answer; failures stay silent for the intake. */
final class NotifyOwnerOfAnswer implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(public readonly string $receipt) {}

    public function handle(OwnerAlerts $alerts): void
    {
        $echo = EchoSubmission::query()->where('public_id', $this->receipt)->first();
        if (! $echo || $echo->isAgent() || ($echo->metadata['owner_notified_at'] ?? null) !== null) {
            return;
        }
        if (! $alerts->notifyAnswer($echo)) {
            return;
        }
        $meta = is_array($echo->metadata) ? $echo->metadata : [];
        $meta['owner_notified_at'] = now()->toIso8601String();
        $echo->forceFill(['metadata' => $meta])->save();
    }
}
