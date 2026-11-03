<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;

/**
 * The site's languages.
 *
 * English is the source text and the fallback for everything. The other
 * nine are the top European/global set Adrian asked for (2026-11-03):
 * Romanian first (the brand's home language), then Spanish, French,
 * German, Italian, Portuguese, Hungarian, Polish and Dutch.
 *
 * Detection is browser-first, as chosen: an explicit choice always wins
 * over detection, and detection never lands on a language we do not have.
 */
final class Locales
{
    public const COOKIE = 'tbwim_locale';

    public const FALLBACK = 'en';

    /** @var array<string, array{native: string, english: string, date_format: string}> */
    public const SUPPORTED = [
        'en' => ['native' => 'English', 'english' => 'English', 'date_format' => 'j M Y'],
        'ro' => ['native' => 'Română', 'english' => 'Romanian', 'date_format' => 'd.m.Y'],
        'es' => ['native' => 'Español', 'english' => 'Spanish', 'date_format' => 'j M Y'],
        'fr' => ['native' => 'Français', 'english' => 'French', 'date_format' => 'j M Y'],
        'de' => ['native' => 'Deutsch', 'english' => 'German', 'date_format' => 'j. M Y'],
        'it' => ['native' => 'Italiano', 'english' => 'Italian', 'date_format' => 'j M Y'],
        'pt' => ['native' => 'Português', 'english' => 'Portuguese', 'date_format' => 'j M Y'],
        'hu' => ['native' => 'Magyar', 'english' => 'Hungarian', 'date_format' => 'Y. M j.'],
        'pl' => ['native' => 'Polski', 'english' => 'Polish', 'date_format' => 'j M Y'],
        'nl' => ['native' => 'Nederlands', 'english' => 'Dutch', 'date_format' => 'j M Y'],
    ];

    public static function isSupported(?string $locale): bool
    {
        return $locale !== null && isset(self::SUPPORTED[strtolower(trim($locale))]);
    }

    /** Normalise `RO-ro` / `pt_BR` to a supported code, or null. */
    public static function normalize(?string $locale): ?string
    {
        if ($locale === null) {
            return null;
        }
        $tag = strtolower(str_replace('_', '-', trim($locale)));
        if ($tag === '' || $tag === '*') {
            return null;
        }
        if (isset(self::SUPPORTED[$tag])) {
            return $tag;
        }
        $primary = explode('-', $tag)[0];

        return isset(self::SUPPORTED[$primary]) ? $primary : null;
    }

    /**
     * Best supported language from an Accept-Language header value.
     * Quality values decide; on a tie the earlier tag wins.
     */
    public static function fromAcceptLanguage(?string $header): string
    {
        if ($header === null || trim($header) === '') {
            return self::FALLBACK;
        }
        $candidates = [];
        foreach (explode(',', $header) as $index => $part) {
            $pieces = array_map('trim', explode(';', trim($part)));
            $tag = $pieces[0] ?? '';
            $quality = 1.0;
            foreach (array_slice($pieces, 1) as $piece) {
                if (str_starts_with($piece, 'q=')) {
                    $quality = max(0.0, min(1.0, (float) substr($piece, 2)));
                }
            }
            if ($tag === '' || $quality <= 0.0) {
                continue;
            }
            $candidates[] = ['tag' => $tag, 'q' => $quality, 'i' => $index];
        }
        usort($candidates, static fn (array $a, array $b): int => ($b['q'] <=> $a['q']) ?: ($a['i'] <=> $b['i']));
        foreach ($candidates as $candidate) {
            $match = self::normalize($candidate['tag']);
            if ($match !== null) {
                return $match;
            }
        }

        return self::FALLBACK;
    }

    /**
     * The locale for this request: explicit `?lang=` first, then the
     * remembered cookie, then the browser's Accept-Language.
     *
     * @return array{locale: string, explicit: bool}
     */
    public static function resolve(Request $request): array
    {
        $choice = $request->query('lang');
        $explicit = self::normalize(is_string($choice) ? $choice : null);
        if ($explicit !== null) {
            return ['locale' => $explicit, 'explicit' => true];
        }
        $cookie = self::normalize($request->cookie(self::COOKIE));
        if ($cookie !== null) {
            return ['locale' => $cookie, 'explicit' => false];
        }

        return ['locale' => self::fromAcceptLanguage($request->header('Accept-Language')), 'explicit' => false];
    }

    public static function dateFormat(string $locale): string
    {
        return self::SUPPORTED[$locale]['date_format'] ?? self::SUPPORTED[self::FALLBACK]['date_format'];
    }
}
