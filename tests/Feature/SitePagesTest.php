<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\QuestionSynthesis;
use App\Models\EchoSubmission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SitePagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_serves_the_million_page(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('A million answers.', false);
    }

    public function test_selecting_a_question_shows_only_its_public_answers(): void
    {
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-10-30 12:00:00', 'Europe/Bucharest'));
        EchoSubmission::create(['body' => 'First question answer.', 'episode_ref' => 'imagine-one-million-euro', 'status' => 'received', 'consent_quote' => true, 'metadata' => ['respondent_type' => 'human'], 'released_at' => now()]);
        EchoSubmission::create(['body' => 'Private second answer.', 'episode_ref' => 'start-when-not-ready', 'status' => 'received', 'consent_quote' => false, 'metadata' => ['respondent_type' => 'human']]);

        $this->get('/?story=start-when-not-ready')->assertOk()
            ->assertSee('id="featured-question-heading">What is the best way you imagine to start something', false)
            ->assertSee('No public answers yet. Be the first')
            ->assertDontSee('First question answer.')
            ->assertDontSee('Private second answer.');
        $this->get('/?story=imagine-one-million-euro')->assertOk()->assertSee('First question answer.');
    }

    public function test_million_page_still_serves(): void
    {
        $this->get('/million')->assertOk()
            ->assertSee('A million answers.', false);
    }

    public function test_arena_selects_open_questions_and_only_shows_their_public_answers(): void
    {
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-10-30 12:00:00', 'Europe/Bucharest'));
        $first = EchoSubmission::create(['body' => 'A public first question answer.', 'episode_ref' => 'imagine-one-million-euro', 'status' => 'received', 'consent_quote' => true, 'metadata' => ['respondent_type' => 'human'], 'released_at' => now()]);
        EchoSubmission::create(['body' => 'A private second question answer.', 'episode_ref' => 'start-when-not-ready', 'status' => 'received', 'consent_quote' => false, 'metadata' => ['respondent_type' => 'human']]);

        $this->get('/arena')->assertOk()->assertSee('No public answers are available yet.')
            ->assertDontSee('A public first question answer.')->assertDontSee('A private second question answer.')
            ->assertSee('What is the best way you imagine to...')
            ->assertSee('value="start-when-not-ready" selected>start something when you don&#039;t feel ready?</option>', false)
            ->assertSee('>make one million euro?</option>', false)
            ->assertDontSee('value="know-when-to-stop"', false);
        $selected = $this->get('/arena?story=imagine-one-million-euro')->assertOk()
            ->assertSee('A public first question answer.')->assertDontSee('A private second question answer.')
            ->assertSee('ai-logos/gemini-color.svg', false)->assertSee('ai-logos/claude-color.svg', false)
            ->assertSee('/assets/adisaan/site/wordmark.svg', false)
            ->assertSee(route('adisaan.public.million', ['story' => 'imagine-one-million-euro']).'#answer-'.$first->id, false);
        $html = $selected->getContent();
        $this->assertSame(1, substr_count($html, 'id="arena-question"'));
        $this->assertGreaterThan(strpos($html, 'class="duel"'), strpos($html, 'id="arena-question"'));
        $this->get('/million?story=imagine-one-million-euro')->assertOk()->assertSee('id="answer-'.$first->id.'"', false);
        $this->get('/')->assertOk()->assertSee('href="'.route('adisaan.public.arena').'"', false);
    }

    public function test_arena_insights_require_ten_public_answers_and_a_public_synthesis(): void
    {
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-10-30 12:00:00', 'Europe/Bucharest'));
        $ref = 'imagine-one-million-euro';
        for ($i = 0; $i < 9; $i++) {
            EchoSubmission::create(['body' => str_repeat('A public human answer. ', 3), 'episode_ref' => $ref, 'status' => 'received', 'consent_quote' => true, 'metadata' => ['respondent_type' => 'human'], 'released_at' => now()]);
        }
        EchoSubmission::create(['body' => str_repeat('A pending private answer. ', 3), 'episode_ref' => $ref, 'status' => 'received', 'consent_quote' => true, 'metadata' => ['respondent_type' => 'human']]);
        $this->artisan('tbwim:synthesize', ['--question' => $ref, '--min-voices' => 10, '--dry-run' => true])->expectsOutputToContain('Below the minimum')->assertSuccessful();
        $synthesis = QuestionSynthesis::create(['question_ref' => $ref, 'version' => 1, 'body' => "Muse insight for this question.\n\nA clear difference.\n\nA distinctive idea.", 'voices_site' => 9, 'voices_agents' => 1, 'themes' => [], 'model' => 'muse', 'prompt_version' => \App\Services\Tbwim\QuestionSynthesizer::PROMPT_VERSION, 'generated_at' => now()]);
        $this->get('/arena?story='.$ref)->assertDontSee('Muse insight for this question.');
        EchoSubmission::create(['body' => str_repeat('A public agent answer. ', 3), 'episode_ref' => $ref, 'status' => 'received', 'consent_quote' => true, 'metadata' => ['respondent_type' => 'agent'], 'released_at' => now()]);
        $this->get('/arena?story='.$ref)->assertSee('Muse insight for this question.');
        $this->get('/arena?story=start-when-not-ready')->assertDontSee('Muse insight for this question.');
        $this->artisan('tbwim:synthesize', ['--question' => $ref, '--min-voices' => 10, '--dry-run' => true])->expectsOutputToContain('next voice milestone')->assertSuccessful();
        $synthesis->update(['prompt_version' => 'breath-v2-meta']);
        $this->get('/arena?story='.$ref)->assertDontSee('Muse insight for this question.');
    }

    public function test_about_redirects_to_brand_home_and_menu_links_there(): void
    {
        $url = rtrim(config('adisaan_public.brand_url'), '/').'/';
        $this->get('/about')->assertRedirect($url);
        $this->get('/')->assertOk()->assertSee('href="'.$url.'">About</a>', false);
    }

    public function test_sitemap_omits_the_redirected_about_page(): void
    {
        $this->get('/sitemap.xml')->assertOk()
            ->assertDontSee(config('tbwim.url').'/about', false);
    }

    public function test_answer_page_points_agents_at_this_apps_endpoint(): void
    {
        config()->set('tbwim.url', 'https://tbwim.adisaan.com');
        config()->set('adisaan_public.url', 'https://tbwim.adisaan.com');
        $this->get('/answer')->assertOk()
            ->assertSee('https://tbwim.adisaan.com/api/agent/answer', false)
            ->assertDontSee('https://adisaan.com/api/agent/answer', false);
    }

    public function test_answer_page_selects_latest_open_question_and_allows_choosing_an_earlier_one(): void
    {
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-10-30 12:00:00', 'Europe/Bucharest'));
        $this->get('/answer')->assertOk()
            ->assertSee('What is the best way to imagine to start something', false)
            ->assertSee('value="start-when-not-ready" selected', false)
            ->assertSee('value="imagine-one-million-euro"', false)
            ->assertDontSee('value="know-when-to-stop"', false);
        $this->get('/answer?story=imagine-one-million-euro')->assertOk()
            ->assertSee('value="imagine-one-million-euro" selected', false)
            ->assertSee('name="episode_ref" value="imagine-one-million-euro"', false);
        $this->get('/answer?story=know-when-to-stop')->assertOk()
            ->assertSee('name="episode_ref" value="start-when-not-ready"', false);
    }

    public function test_home_faq_points_agents_at_this_apps_endpoint(): void
    {
        config()->set('tbwim.url', 'https://tbwim.adisaan.com');
        config()->set('adisaan_public.url', 'https://tbwim.adisaan.com');
        $this->get('/construction')->assertOk()
            ->assertSee('tbwim.adisaan.com/api/agent/answer', false)
            ->assertDontSee('at adisaan.com/api/agent/answer', false);
    }

    public function test_million_page_hides_the_breathing_answer_when_a_version_exists(): void
    {
        QuestionSynthesis::create([
            'question_ref' => (string) config('adisaan_public.home.episode_ref'),
            'version' => 3,
            'body' => 'Every voice imagines a slower morning. TEST-SYNTHESIS-BODY.',
            'voices_site' => 7,
            'voices_agents' => 0,
            'themes' => [['theme' => 'Slowness', 'voices' => 7]],
            'model' => 'muse-spark-1.3',
            'prompt_version' => 'v1',
            'generated_at' => now(),
        ]);

        $this->get('/')->assertOk()
            ->assertDontSee('TEST-SYNTHESIS-BODY', false)
            ->assertDontSee('id="breath"', false);
    }

    public function test_million_page_hides_the_breath_block_without_a_version(): void
    {
        $this->assertSame(0, QuestionSynthesis::count());

        $this->get('/')->assertOk()
            ->assertDontSee('id="breath"', false);
    }
}
