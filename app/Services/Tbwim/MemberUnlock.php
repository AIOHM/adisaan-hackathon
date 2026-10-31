<?php

declare(strict_types=1);

namespace App\Services\Tbwim;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

/**
 * Submit-to-unlock: whoever answers any question can read the
 * members-only answers. No accounts, no member area — the receipt is
 * the key, carried in a signed cookie.
 *
 * Same HMAC pattern as VisitorIdentity: the value is receipt.hmac and
 * a forged cookie reads as no cookie. Clearing it re-locks the wall,
 * which is exactly the new-visitor experience.
 */
final class MemberUnlock
{
    public const COOKIE = 'tbwim_member';

    /** Two years: an answerer keeps their seat for the whole movement. */
    private const LIFETIME_MINUTES = 60 * 24 * 730;

    /** Grant the unlock for this receipt; the cookie rides the response. */
    public function grant(string $receipt): void
    {
        if (trim($receipt) === '') {
            return;
        }

        Cookie::queue(Cookie::make(
            self::COOKIE,
            $this->sign($receipt),
            self::LIFETIME_MINUTES,
            null,
            null,
            true,   // secure
            true,   // httpOnly: no script needs to read this
            false,
            'Lax'
        ));
    }

    /** True when this request carries a genuinely granted unlock. */
    public function unlocked(?Request $request = null): bool
    {
        return $this->existing($request) !== null;
    }

    /** The unlocking receipt already on this request, without granting. */
    public function existing(?Request $request = null): ?string
    {
        $request ??= request();

        return $request instanceof Request
            ? $this->read((string) $request->cookie(self::COOKIE, ''))
            : null;
    }

    private function sign(string $receipt): string
    {
        return $receipt.'.'.hash_hmac('sha256', $receipt, (string) config('app.key'));
    }

    private function read(string $raw): ?string
    {
        if ($raw === '' || ! str_contains($raw, '.')) {
            return null;
        }

        [$receipt, $signature] = explode('.', $raw, 2);
        if ($receipt === '' || ! hash_equals(hash_hmac('sha256', $receipt, (string) config('app.key')), $signature)) {
            return null;
        }

        return $receipt;
    }
}
