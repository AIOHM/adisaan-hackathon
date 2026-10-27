<?php

declare(strict_types=1);

namespace App\Services\AdiSaan;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The gratitude ledger: rows in, balance out.
 *
 * There is no stored total anywhere. A balance is the sum of the rows, so
 * every token can be traced to the act that earned it — people will ask
 * where their tokens went and the honest answer has to exist. Spending is
 * the same table with a negative amount, so there is one history and no
 * second place for a balance to disagree with itself.
 *
 * Two rules the rest of the site depends on:
 *
 *  - **Awards are idempotent.** One answer on one question earns once,
 *    however many times the thank-you page is reloaded. Enforced by a unique
 *    index, not by remembering to check, so a race cannot double-pay.
 *  - **Unclaimed tokens are pending.** They are counted and shown, because
 *    that is the reason to claim them, but they cannot be spent until a name
 *    and email bind the trail to a person. Without that, anyone can clear a
 *    cookie and farm the first-answer bonus forever.
 */
final class GratitudeLedger
{
    private const TABLE = 'gratitude_entries';

    public function enabled(): bool
    {
        return (bool) config('adisaan_public.gratitude.enabled', false)
            && Schema::hasTable(self::TABLE);
    }

    /**
     * Credit a visitor for something they did.
     *
     * Returns the tokens actually granted — 0 when the reason is unknown,
     * the ledger is off, or this visitor has already been paid for this
     * exact subject. Never throws for a duplicate: being paid twice is the
     * bug, being told about it is not the caller's problem.
     */
    public function award(
        string $visitorId,
        string $reason,
        ?string $subjectType = null,
        ?string $subjectId = null,
        bool $confirmed = true,
        array $metadata = []
    ): int {
        if ($visitorId === '' || ! $this->enabled()) {
            return 0;
        }

        $amount = (int) config('adisaan_public.gratitude.earn.'.$reason, 0);
        if ($amount <= 0) {
            return 0;
        }

        // A returning member keeps earning into the same usable wallet.
        $claimed = DB::table(self::TABLE)
            ->where('visitor_id', $visitorId)
            ->whereNotNull('claimed_at')
            ->orderByDesc('id')
            ->first(['claimed_email', 'claimed_at']);

        try {
            DB::table(self::TABLE)->insert([
                'visitor_id' => $visitorId,
                'claimed_email' => $claimed?->claimed_email,
                'claimed_at' => $claimed?->claimed_at,
                'reason' => $reason,
                'amount' => $amount,
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'confirmed' => $confirmed,
                'metadata' => $metadata === [] ? null : json_encode($metadata),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (QueryException $e) {
            // The unique index did its job: already paid for this subject.
            return 0;
        }

        return $amount;
    }

    /**
     * Spend tokens. Returns false and writes nothing when the balance will
     * not cover it, so a caller cannot half-spend.
     *
     * Only claimed tokens count toward what can be spent.
     */
    public function spend(string $visitorId, string $what, string $subjectId, array $metadata = []): bool
    {
        if ($visitorId === '' || ! $this->enabled()) {
            return false;
        }

        $cost = (int) config('adisaan_public.gratitude.spend.'.$what, 0);
        if ($cost <= 0 || $this->spendableBalance($visitorId) < $cost) {
            return false;
        }

        return DB::transaction(function () use ($visitorId, $what, $subjectId, $cost, $metadata): bool {
            // Re-read inside the transaction: two tabs pressing the same
            // button must not both succeed on one balance.
            if ($this->spendableBalance($visitorId) < $cost) {
                return false;
            }

            // The spend row is claimed by definition — only a claimed
            // visitor can reach here — and it must say so, because
            // spendableBalance() counts claimed rows only. Written
            // unclaimed, the deduction would simply not count and the
            // balance would never go down.
            $claimedEmail = DB::table(self::TABLE)
                ->where('visitor_id', $visitorId)
                ->whereNotNull('claimed_at')
                ->value('claimed_email');

            DB::table(self::TABLE)->insert([
                'visitor_id' => $visitorId,
                'reason' => 'spend',
                'amount' => -$cost,
                'subject_type' => $what,
                'subject_id' => $subjectId,
                'confirmed' => true,
                'claimed_email' => $claimedEmail,
                'claimed_at' => now(),
                'metadata' => $metadata === [] ? null : json_encode($metadata),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return true;
        });
    }

    /**
     * Bind an anonymous trail to a person, and pay for doing it.
     *
     * Everything this visitor already earned becomes spendable in one
     * statement, which is the whole point of the pending state.
     */
    public function claim(string $visitorId, string $email): int
    {
        if ($visitorId === '' || $email === '' || ! $this->enabled()) {
            return 0;
        }

        // Award first, then mark everything claimed — including the bonus
        // row just written. The other order leaves the claim bonus itself
        // unclaimed and therefore unspendable, which is exactly the kind of
        // off-by-one an economy cannot afford.
        $bonus = $this->award($visitorId, 'claim', 'email', $email);

        DB::table(self::TABLE)
            ->where('visitor_id', $visitorId)
            ->whereNull('claimed_at')
            ->update(['claimed_email' => $email, 'claimed_at' => now(), 'updated_at' => now()]);

        return $bonus;
    }

    public function isClaimed(string $visitorId): bool
    {
        return $this->enabled() && $visitorId !== '' && DB::table(self::TABLE)
            ->where('visitor_id', $visitorId)
            ->whereNotNull('claimed_at')
            ->exists();
    }

    /** Everything earned and confirmed, claimed or not. */
    public function balance(string $visitorId): int
    {
        if ($visitorId === '' || ! $this->enabled()) {
            return 0;
        }

        return (int) DB::table(self::TABLE)
            ->where('visitor_id', $visitorId)
            ->where('confirmed', true)
            ->sum('amount');
    }

    /** What can actually be spent: confirmed and claimed. */
    public function spendableBalance(string $visitorId): int
    {
        if ($visitorId === '' || ! $this->enabled()) {
            return 0;
        }

        return (int) DB::table(self::TABLE)
            ->where('visitor_id', $visitorId)
            ->where('confirmed', true)
            ->whereNotNull('claimed_at')
            ->sum('amount');
    }

    /**
     * Earned but not yet usable: either awaiting review, or awaiting a name
     * and email. Shown to the visitor, because it is the reason to claim.
     */
    public function pendingBalance(string $visitorId): int
    {
        return max(0, $this->balance($visitorId) - $this->spendableBalance($visitorId))
            + $this->unconfirmedTotal($visitorId);
    }

    private function unconfirmedTotal(string $visitorId): int
    {
        if ($visitorId === '' || ! $this->enabled()) {
            return 0;
        }

        return (int) DB::table(self::TABLE)
            ->where('visitor_id', $visitorId)
            ->where('confirmed', false)
            ->sum('amount');
    }

    /**
     * The visitor's own history, newest first — what they did and what it
     * paid. This is what makes the balance answerable rather than magic.
     *
     * @return list<array{reason: string, amount: int, created_at: ?string, pending: bool}>
     */
    public function history(string $visitorId, int $limit = 20): array
    {
        if ($visitorId === '' || ! $this->enabled()) {
            return [];
        }

        return DB::table(self::TABLE)
            ->where('visitor_id', $visitorId)
            ->orderByDesc('id')
            ->limit($limit)
            ->get(['reason', 'amount', 'confirmed', 'claimed_at', 'created_at'])
            ->map(static fn ($row): array => [
                'reason' => (string) $row->reason,
                'amount' => (int) $row->amount,
                'created_at' => $row->created_at ? (string) $row->created_at : null,
                'pending' => ! $row->confirmed || $row->claimed_at === null,
            ])
            ->all();
    }

    /**
     * Which questions this visitor has answered.
     *
     * Read out of the award metadata rather than stored again, so there is
     * one record of an answer and no second list to fall out of step with
     * it. The episode ref is written there when the answer is paid for.
     *
     * Counting in PHP because the ref lives inside a JSON column and the
     * extraction has to behave the same on every driver. Right at this
     * scale; at a million rows this becomes its own column.
     *
     * @return list<string>
     */
    public function answeredQuestions(string $visitorId): array
    {
        if ($visitorId === '' || ! $this->enabled()) {
            return [];
        }

        return DB::table(self::TABLE)
            ->where('visitor_id', $visitorId)
            ->where('reason', 'answer')
            ->whereNotNull('metadata')
            ->pluck('metadata')
            ->map(static function ($raw): ?string {
                $meta = json_decode((string) $raw, true);

                return is_array($meta) && ! empty($meta['episode_ref'])
                    ? (string) $meta['episode_ref']
                    : null;
            })
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /** Confirm awards held back pending review, e.g. once an answer passes. */
    public function confirmFor(string $subjectType, string $subjectId): int
    {
        if (! $this->enabled()) {
            return 0;
        }

        return DB::table(self::TABLE)
            ->where('subject_type', $subjectType)
            ->where('subject_id', $subjectId)
            ->where('confirmed', false)
            ->update(['confirmed' => true, 'updated_at' => now()]);
    }
}
