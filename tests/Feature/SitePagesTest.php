<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\QuestionSynthesis;
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

    public function test_million_page_still_serves(): void
    {
        $this->get('/million')->assertOk()
            ->assertSee('A million answers.', false);
    }

    public function test_about_page_introduces_the_project_with_avatar(): void
    {
        $this->get('/about')->assertOk()
            ->assertSee('One question, a million answers.', false)
            ->assertSee('/assets/adisaan/site/avatar-adisaan.jpg', false)
            ->assertSee('>About</a>', false);
    }

    public function test_sitemap_lists_the_about_page(): void
    {
        $this->get('/sitemap.xml')->assertOk()
            ->assertSee(config('tbwim.url').'/about', false);
    }

    public function test_answer_page_points_agents_at_this_apps_endpoint(): void
    {
        config()->set('tbwim.url', 'https://tbwim.adisaan.com');
        config()->set('adisaan_public.url', 'https://tbwim.adisaan.com');
        $this->get('/answer')->assertOk()
            ->assertSee('https://tbwim.adisaan.com/api/agent/answer', false)
            ->assertDontSee('https://adisaan.com/api/agent/answer', false);
    }

    public function test_home_faq_points_agents_at_this_apps_endpoint(): void
    {
        config()->set('tbwim.url', 'https://tbwim.adisaan.com');
        config()->set('adisaan_public.url', 'https://tbwim.adisaan.com');
        $this->get('/construction')->assertOk()
            ->assertSee('tbwim.adisaan.com/api/agent/answer', false)
            ->assertDontSee('at adisaan.com/api/agent/answer', false);
    }

    public function test_million_page_shows_the_breathing_answer_when_a_version_exists(): void
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
            ->assertSee('TEST-SYNTHESIS-BODY', false)
            ->assertSee('Version 3', false);
    }

    public function test_million_page_hides_the_breath_block_without_a_version(): void
    {
        $this->assertSame(0, QuestionSynthesis::count());

        $this->get('/')->assertOk()
            ->assertDontSee('id="breath"', false);
    }
}
