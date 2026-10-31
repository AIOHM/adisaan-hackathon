<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Tbwim\OwnerAlerts;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

/** Pings the owner about one new join-lead email. */
final class NotifyOwnerOfLead implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(public readonly string $email) {}

    public function handle(OwnerAlerts $alerts): void
    {
        $lead = DB::table('join_leads')->where('email', $this->email)->first();
        if (! $lead || $lead->owner_notified_at !== null) {
            return;
        }
        if (! $alerts->notifyLead($lead->email, (string) ($lead->source_channel ?? ''))) {
            return;
        }
        DB::table('join_leads')->where('email', $this->email)->update(['owner_notified_at' => now()]);
    }
}
