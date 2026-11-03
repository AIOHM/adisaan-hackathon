<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Locales;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * One language per response, chosen before anything renders.
 *
 * Order: `?lang=` (remembered in a cookie for a year), the cookie, then
 * the browser's Accept-Language. English is the fallback and the source
 * text, so a missing translation can never break a page — Laravel falls
 * back to the English file, and the config copy stays English underneath.
 */
final class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $resolved = Locales::resolve($request);
        $locale = $resolved['locale'];

        app()->setLocale($locale);
        Carbon::setLocale($locale);
        $request->attributes->set('locale', $locale);

        View::share('locale', $locale);
        View::share('locales', Locales::SUPPORTED);
        View::share('localeDateFormat', Locales::dateFormat($locale));

        $response = $next($request);

        if ($resolved['explicit']) {
            $response->headers->setCookie(cookie(
                Locales::COOKIE,
                $locale,
                60 * 24 * 365,
                '/',
                null,
                $request->isSecure(),
                true,
                false,
                'lax',
            ));
        }
        $response->headers->set('Content-Language', $locale);
        $vary = (string) $response->headers->get('Vary', '');
        if (! str_contains(strtolower($vary), 'accept-language')) {
            $response->headers->set('Vary', trim($vary === '' ? 'Accept-Language, Cookie' : $vary.', Accept-Language, Cookie'));
        }

        return $response;
    }
}
