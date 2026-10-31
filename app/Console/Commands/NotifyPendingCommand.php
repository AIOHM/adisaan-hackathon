<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\EchoSubmission;
use App\Services\Tbwim\OwnerAlerts;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/** Catches human answers and join emails the queue never announced. */
final class NotifyPendingCommand extends Command
{
    protected $signature = 'tbwim:notify-pending {--limit=25}';

    protected $description = 'Notify the owner about unannounced human answers and join emails';

    public function handle(OwnerAlerts $alerts): int
    {
        if (! $alerts->configured()) {
            $this->info('Owner alerts are not configured.');

            return self::SUCCESS;
        }
        $limit = max(1, (int) $this->option('limit'));
        $cutoff = now()->subMinutes(10);
        $reviewGrace = now()->subMinutes(30);
        $sent = 0;
        $answers = EchoSubmission::query()->humans()
            ->where('status', EchoSubmission::STATUS_RECEIVED)
            ->whereNull('metadata->owner_notified_at')
            ->whereNull('metadata->decided_by')
            // Held by the doorkeeper stays silent. Unreviewed rows get a
            // grace period for the review job; past it the owner hears
            // anyway, because silence about a stuck queue is worse.
            ->where(function ($query): void {
                $query->where('metadata->review_status', '!=', 'held')->orWhereNull('metadata->review_status');
            })
            ->where(function ($query) use ($reviewGrace): void {
                $query->whereNotNull('reviewed_at')->orWhere('created_at', '<', $reviewGrace);
            })
            ->where('created_at', '<', $cutoff)
            ->orderBy('created_at')->limit($limit)->get();
        foreach ($answers as $echo) {
            if (! $alerts->notifyAnswer($echo)) {
                continue;
            }
            $meta = is_array($echo->metadata) ? $echo->metadata : [];
            $meta['owner_notified_at'] = now()->toIso8601String();
            $echo->forceFill(['metadata' => $meta])->save();
            $sent++;
        }
        $leads = DB::table('join_leads')->whereNull('owner_notified_at')->where('created_at', '<', $cutoff)->orderBy('created_at')->limit($limit)->get();
        foreach ($leads as $lead) {
            if (! $alerts->notifyLead($lead->email, (string) ($lead->source_channel ?? ''))) {
                continue;
            }
            DB::table('join_leads')->where('email', $lead->email)->update(['owner_notified_at' => now()]);
            $sent++;
        }
        $this->info(sprintf('%d notified', $sent));

        return self::SUCCESS;
    }
}
