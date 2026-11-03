<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\EchoSubmission;
use App\Support\Locales;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

final class LocaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_accept_language_picks_the_best_supported_language(): void
    {
        $this->assertSame('ro', Locales::fromAcceptLanguage('ro-RO,ro;q=0.9,en;q=0.8'));
        $this->assertSame('es', Locales::fromAcceptLanguage('es-MX'));
        $this->assertSame('fr', Locales::fromAcceptLanguage('en;q=0.5,fr;q=0.9'));
        $this->assertSame('en', Locales::fromAcceptLanguage('ja-JP,ja;q=0.9'));
        $this->assertSame('en', Locales::fromAcceptLanguage(null));
        $this->assertSame('en', Locales::fromAcceptLanguage('ro;q=0,en;q=0.5'));
    }

    public function test_romanian_browser_sees_romanian_chrome_and_question(): void
    {
        $this->withHeaders(['Accept-Language' => 'ro-RO,ro;q=0.9'])
            ->get('/about')
            ->assertOk()
            ->assertSee('<html lang="ro">', false)
            ->assertSee('Întrebarea zilei', false)
            ->assertSee('Care este cel mai bun mod în care îți imaginezi să faci un milion de euro?', false)
            ->assertHeader('Content-Language', 'ro');
        $this->withHeaders(['Accept-Language' => 'ro-RO,ro;q=0.9'])
            ->get('/')
            ->assertOk()
            ->assertSee('Un milion de răspunsuri.', false)
            ->assertSee('Muncă', false)
            ->assertDontSee('01 Work', false);
    }

    public function test_czech_browser_sees_czech_chrome(): void
    {
        $this->withHeaders(['Accept-Language' => 'cs-CZ'])
            ->get('/arena')
            ->assertOk()
            ->assertSee('<html lang="cs">', false)
            ->assertSee('<title>AdiSaan Spark Aréna</title>', false);
    }

    public function test_spanish_browser_sees_spanish_chrome(): void
    {
        $this->withHeaders(['Accept-Language' => 'es-ES'])
            ->get('/question')
            ->assertOk()
            ->assertSee('<html lang="es">', false)
            ->assertSee('Explorar las preguntas', false);
    }

    public function test_explicit_choice_wins_and_is_remembered(): void
    {
        $response = $this->withHeaders(['Accept-Language' => 'ro'])
            ->get('/?lang=fr')
            ->assertOk()
            ->assertSee('<html lang="fr">', false)
            ->assertCookie(Locales::COOKIE, 'fr');

        $this->withCookie(Locales::COOKIE, 'fr')
            ->withHeaders(['Accept-Language' => 'ro'])
            ->get('/')
            ->assertOk()
            ->assertSee('<html lang="fr">', false);
    }

    public function test_unsupported_browser_language_falls_back_to_english(): void
    {
        $this->withHeaders(['Accept-Language' => 'ja-JP'])
            ->get('/')
            ->assertOk()
            ->assertSee('<html lang="en">', false)
            ->assertSee('A million answers.', false);
    }

    public function test_every_supported_locale_renders_the_public_pages(): void
    {
        foreach (array_keys(Locales::SUPPORTED) as $locale) {
            $this->withCookie(Locales::COOKIE, $locale)->get('/')->assertOk()->assertSee('<html lang="'.$locale.'">', false);
            $this->withCookie(Locales::COOKIE, $locale)->get('/question')->assertOk();
            $this->withCookie(Locales::COOKIE, $locale)->get('/answer')->assertOk();
            $this->withCookie(Locales::COOKIE, $locale)->get('/manifesto')->assertOk();
            $this->withCookie(Locales::COOKIE, $locale)->get('/privacy')->assertOk();
        }
    }

    public function test_answers_stay_in_the_language_they_were_written_in(): void
    {
        $this->travelTo(Carbon::parse('2026-10-30 12:00:00', 'Europe/Bucharest'));
        EchoSubmission::create([
            'body' => 'Răspunsul meu rămâne în română, exact cum l-am scris.',
            'episode_ref' => 'imagine-one-million-euro',
            'status' => 'received',
            'consent_quote' => true,
            'released_at' => now(),
            'reviewed_at' => now(),
            'metadata' => ['respondent_type' => 'human', 'review_status' => 'approved'],
        ]);

        // Even on the German site the words themselves are not translated.
        $this->withCookie(Locales::COOKIE, 'de')
            ->get('/?story=imagine-one-million-euro')
            ->assertOk()
            ->assertSee('Răspunsul meu rămâne în română, exact cum l-am scris.', false);
    }

    public function test_german_manifesto_renders_german_long_form(): void
    {
        $this->withCookie(Locales::COOKIE, 'de')
            ->get('/manifesto')
            ->assertOk()
            ->assertSee('Nimm Lehren niemals wörtlich', false)
            ->assertSee('Auf dieser Seite', false);
    }

    public function test_the_arena_is_the_spark_arena(): void
    {
        $this->get('/arena')->assertOk()
            ->assertSee('<title>AdiSaan Spark Arena</title>', false)
            ->assertSee('>AdiSaan Spark Arena</h2>', false);
        $this->withHeaders(['Accept-Language' => 'ro-RO'])
            ->get('/arena')
            ->assertOk()
            ->assertSee('<title>Arena Spark AdiSaan</title>', false);
    }

    public function test_language_switcher_lists_every_language(): void
    {
        $response = $this->get('/')->assertOk();
        foreach (Locales::SUPPORTED as $code => $meta) {
            $response->assertSee($meta['native'], false);
        }
        $response->assertSee('?lang=ro', false);
    }

    public function test_malformed_language_choice_falls_back_without_crashing(): void
    {
        $this->withHeaders(['Accept-Language' => 'ro'])->get('/?lang[]=de')
            ->assertOk()->assertSee('<html lang="ro">', false);
    }
}
