<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AnswerCountdownTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_countdown_is_a_rolling_sixty_seconds(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $found = preg_match(
            '/data-answer-countdown[^>]*data-deadline="([^"]+)"[^>]*data-server-now="([^"]+)"/',
            $html,
            $match
        );
        $this->assertSame(1, $found, 'Homepage must render the answer countdown clock.');

        $deadline = strtotime($match[1]);
        $serverNow = strtotime($match[2]);
        $this->assertIsInt($deadline);
        $this->assertIsInt($serverNow);
        // Rolling window: sixty seconds from render time, with a small
        // tolerance for the milliseconds between the two now() calls.
        $this->assertGreaterThanOrEqual(55, $deadline - $serverNow);
        $this->assertLessThanOrEqual(65, $deadline - $serverNow);
    }

    public function test_homepage_countdown_reveals_into_fresh_answers(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('FRESH ANSWERS IN', false)
            ->assertSee('Fresh answers land when the clock reaches zero.', false);
    }
}
