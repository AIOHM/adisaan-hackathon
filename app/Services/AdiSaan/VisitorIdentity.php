<?php

declare(strict_types=1);

namespace App\Services\AdiSaan;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

/**
 * Who earned a token, when the answer form asks for no name.
 *
 * The answer form is anonymous on purpose — one question, one answer, the
 * lowest ask the site makes. Tokens still need somewhere to land, so every
 * visitor carries an opaque id in a signed cookie. The id is random and
 * means nothing on its own: no name, no email, nothing derived from the
 * person. It exists so a trail of answers can later be claimed by whoever
 * made it.
 *
 * Signed with the same HMAC pattern the member pass already uses, so a
 * visitor cannot award themselves an id that belongs to someone else.
 * Clearing the cookie loses the trail, which is exactly why tokens stay
 * pending until a name and email claim them.
 */
final class VisitorIdentity
{
    public const COOKIE = 'adisaan_visitor';

    /** Two years: long enough that a returning answerer keeps their trail. */
    private const LIFETIME_MINUTES = 60 * 24 * 730;

    /**
     * The current visitor's id, minting one if this is their first answer.
     *
     * Returns null only when there is no request to read, so callers in
     * console context get nothing rather than a fabricated visitor.
     */
    public function current(?Request $request = null): ?string
    {
        $request ??= request();
        if (! $request instanceof Request) {
            return null;
        }

        $existing = $this->read((string) $request->cookie(self::COOKIE, ''));
        if ($existing !== null) {
            return $existing;
        }

        $id = (string) Str::ulid();
        // Queued rather than returned: the caller is a controller action that
        // already has its own response, and this must not change that shape.
        Cookie::queue(Cookie::make(
            self::COOKIE,
            $this->sign($id),
            self::LIFETIME_MINUTES,
            null,
            null,
            true,   // secure
            true,   // httpOnly: no script needs to read this
            false,
            'Lax'
        ));

        return $id;
    }

    /** The id already on this request, without minting a new one. */
    public function existing(?Request $request = null): ?string
    {
        $request ??= request();

        return $request instanceof Request
            ? $this->read((string) $request->cookie(self::COOKIE, ''))
            : null;
    }

    private function sign(string $id): string
    {
        return $id.'.'.hash_hmac('sha256', $id, (string) config('app.key'));
    }

    private function read(string $raw): ?string
    {
        if ($raw === '' || ! str_contains($raw, '.')) {
            return null;
        }

        [$id, $signature] = explode('.', $raw, 2);
        if ($id === '' || ! hash_equals(hash_hmac('sha256', $id, (string) config('app.key')), $signature)) {
            return null;
        }

        return $id;
    }
}
