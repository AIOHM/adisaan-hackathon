<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Tbwim\Questions;
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

    public function test_q3_answer_streams_from_the_brand_and_its_brand_poster_wins(): void
    {
        // A generated poster exists for question three on purpose: the
        // story's own brand poster must still be the one shown.
        $generated = public_path('assets/tbwim/posters/know-when-to-stop.webp');
        if (! is_dir(dirname($generated))) {
            mkdir(dirname($generated), 0755, true);
        }
        file_put_contents($generated, 'generated');
        try {
            $this->travelTo(Carbon::parse('2026-11-05 12:00:00', 'Europe/Bucharest'));
            $this->get('/question')->assertOk()
                ->assertSee('https://adisaan.com/question/answer-video/know-when-to-stop', false)
                ->assertSee('question-03-know-when-to-stop-poster.jpg')
                ->assertDontSee('assets/tbwim/posters/know-when-to-stop.webp');
            $poster = app(Questions::class)->find('know-when-to-stop')['poster'] ?? '';
            $this->assertStringEndsWith('question-03-know-when-to-stop-poster.jpg', $poster);
        } finally {
            @unlink($generated);
        }
    }

    public function test_each_question_film_carries_its_own_captions_to_the_stage(): void
    {
        $this->get('/question')->assertOk()
            ->assertSee('data-ep-captions="/assets/adisaan/site/question-02-start-when-not-ready.vtt"', false)
            ->assertSee('data-ep-captions="/assets/adisaan/site/question-03-know-when-to-stop.vtt"', false);
        foreach (['question-02-start-when-not-ready', 'question-03-know-when-to-stop'] as $name) {
            $vtt = public_path("assets/adisaan/site/{$name}.vtt");
            $this->assertFileExists($vtt);
            $this->assertStringStartsWith('WEBVTT', (string) file_get_contents($vtt));
        }
    }
}
