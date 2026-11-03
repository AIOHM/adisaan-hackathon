<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

final class QuestionSlugTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_question_slug_serves_that_question_with_its_own_canonical_and_title(): void
    {
        $this->travelTo(Carbon::parse('2026-11-05 12:00:00', 'Europe/Bucharest'));

        $this->get('/question/know-when-to-stop')->assertOk()
            ->assertSee('<link rel="canonical" href="https://tbwim.adisaan.com/question/know-when-to-stop">', false)
            ->assertSee('id="question-heading" data-stage-title>What is the best way you imagine to know when to stop?</h1>', false)
            ->assertSee('<title>What is the best way you imagine to know when to stop? — AdiSaan</title>', false)
            ->assertSee('data-initial-index="2"', false);
    }

    public function test_an_unknown_question_slug_is_not_found(): void
    {
        $this->travelTo(Carbon::parse('2026-11-05 12:00:00', 'Europe/Bucharest'));

        $this->get('/question/not-a-question')->assertNotFound();
    }

    public function test_the_question_hub_still_serves_the_featured_question(): void
    {
        $this->travelTo(Carbon::parse('2026-11-05 12:00:00', 'Europe/Bucharest'));

        $this->get('/question')->assertOk()
            ->assertSee('id="question-heading"', false);
    }

    public function test_the_sitemap_lists_each_open_question_slug(): void
    {
        $this->travelTo(Carbon::parse('2026-11-05 12:00:00', 'Europe/Bucharest'));

        $this->get('/sitemap.xml')->assertOk()
            ->assertSee('https://tbwim.adisaan.com/question/know-when-to-stop', false)
            ->assertSee('https://tbwim.adisaan.com/question/imagine-one-million-euro', false);
    }
}
