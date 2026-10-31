<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

final class AnswerCountdownTest extends TestCase
{
    use RefreshDatabase;

    public function test_prepared_answer_has_countdown_and_video(): void
    {
        // The answer videos are production media and stay out of git
        // (.gitignore: public/assets/adisaan/site/*.mp4). Without them the
        // reel honestly renders no player, so there is nothing to count down.
        foreach (['question-01-answer.mp4', 'question-02-answer.mp4'] as $video) {
            if (! is_file(public_path('assets/adisaan/site/'.$video))) {
                $this->markTestSkipped("Answer video {$video} is not present in this checkout.");
            }
        }

        $this->travelTo(Carbon::parse('2026-10-30 12:00:00', 'Europe/Bucharest'));
        $this->get('/question')->assertOk()
            ->assertSee('We are preparing the video for you.')
            ->assertSee('data-countdown-seconds>10', false)
            ->assertSee('data-answer-main controls playsinline preload="metadata" hidden src="/assets/adisaan/site/question-01-answer.mp4"', false)
            ->assertSee('data-answer-main controls playsinline preload="metadata" hidden src="/assets/adisaan/site/question-02-answer.mp4"', false)
            ->assertDontSee('Running an AI agent?');
    }
}
