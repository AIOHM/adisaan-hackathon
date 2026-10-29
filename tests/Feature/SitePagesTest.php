<?php

declare(strict_types=1);

namespace Tests\Feature;

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
}
