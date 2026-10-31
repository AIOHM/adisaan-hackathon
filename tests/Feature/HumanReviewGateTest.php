<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\NotifyOwnerOfAnswer;
use App\Jobs\ReviewHumanAnswer;
use App\Models\EchoSubmission;
use App\Services\Tbwim\ReviewService;
use App\Support\FormProofInspector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class HumanReviewGateTest extends TestCase
{
    use RefreshDatabase;

    private function proof(): string
    {
        Carbon::setTestNow(now()->subSeconds(10));
        $token = app(FormProofInspector::class)->issueToken('tbwim_answer');
        Carbon::setTestNow();

        return $token;
    }

    private function humanAnswer(array $over = []): EchoSubmission
    {
        return EchoSubmission::create(array_merge([
            'body' => 'Plant something small and tend it every morning without fail.',
            'episode_ref' => 'imagine-one-million-euro',
            'prompt_text' => 'What is the best way you imagine to make one million euro?',
            'status' => 'received',
            'consent_quote' => true,
            'metadata' => ['respondent_type' => 'human', 'review_status' => 'pending'],
        ], $over));
    }

    private function fakeVerdict(string $json): void
    {
        config()->set('tbwim.meta.api_key', 'test-key');
        config()->set('tbwim.meta.monthly_limit_usd', 5);
        Http::fake(['api.meta.ai/*' => Http::response(['model' => 'muse-spark-1.3',
            'choices' => [['message' => ['content' => $json]]],
            'usage' => ['prompt_tokens' => 500, 'completion_tokens' => 60]])]);
    }

    public function test_repeat_post_quarantines_as_a_duplicate_burst(): void
    {
        Queue::fake();
        $payload = ['body' => 'i dont know how i would do it at all really', 'episode_ref' => 'imagine-one-million-euro', 'share_mode' => 'public', '_answer_form_token' => $this->proof()];
        $this->postJson('/answer', $payload)->assertCreated();
        $this->postJson('/answer', array_merge($payload, ['_answer_form_token' => $this->proof()]))->assertCreated();

        $this->assertSame('received', EchoSubmission::query()->oldest('id')->firstOrFail()->status);
        $second = EchoSubmission::query()->latest('id')->firstOrFail();
        $this->assertSame('quarantined', $second->status);
        $this->assertSame('duplicate_burst', $second->quarantine_reason);
        Queue::assertPushed(ReviewHumanAnswer::class, 1);
    }

    public function test_review_job_notifies_when_the_answer_passes(): void
    {
        Queue::fake();
        $this->fakeVerdict('{"release": true, "reason": "", "confidence": 92, "on_topic": 90, "genuine": 85, "corrected": "Plant something small.", "note": "ok"}');
        $echo = $this->humanAnswer();

        (new ReviewHumanAnswer($echo->public_id))->handle(app(ReviewService::class));

        $this->assertSame('recommended', $echo->fresh()->metadata['review_status']);
        Queue::assertPushed(NotifyOwnerOfAnswer::class, fn (NotifyOwnerOfAnswer $job) => $job->receipt === $echo->public_id);
    }

    public function test_review_job_stays_silent_when_the_answer_is_held(): void
    {
        Queue::fake();
        $this->fakeVerdict('{"release": false, "reason": "off_topic", "confidence": 90, "on_topic": 10, "genuine": 80, "corrected": "", "note": "not about the question"}');
        $echo = $this->humanAnswer();

        (new ReviewHumanAnswer($echo->public_id))->handle(app(ReviewService::class));

        $this->assertSame('held', $echo->fresh()->metadata['review_status']);
        Queue::assertNotPushed(NotifyOwnerOfAnswer::class);
    }

    public function test_review_job_fails_open_when_the_reviewer_is_down(): void
    {
        Queue::fake();
        config()->set('tbwim.meta.api_key', 'test-key');
        config()->set('tbwim.meta.monthly_limit_usd', 5);
        Http::fake(['api.meta.ai/*' => Http::response('down', 500)]);
        $echo = $this->humanAnswer();

        (new ReviewHumanAnswer($echo->public_id))->handle(app(ReviewService::class));

        Queue::assertPushed(NotifyOwnerOfAnswer::class);
    }

    public function test_review_job_trusts_an_existing_hold_without_calling_the_model(): void
    {
        Queue::fake();
        Http::fake();
        $echo = $this->humanAnswer(['reviewed_at' => now(), 'metadata' => ['respondent_type' => 'human', 'review_status' => 'held']]);

        (new ReviewHumanAnswer($echo->public_id))->handle(app(ReviewService::class));

        Queue::assertNotPushed(NotifyOwnerOfAnswer::class);
        Http::assertNothingSent();
    }

    public function test_review_job_skips_what_needs_no_ping(): void
    {
        Queue::fake();
        Http::fake();
        foreach ([
            $this->humanAnswer(['status' => 'quarantined']),
            $this->humanAnswer(['metadata' => ['respondent_type' => 'human', 'review_status' => 'approved', 'decided_by' => 'human']]),
            $this->humanAnswer(['metadata' => ['respondent_type' => 'human', 'review_status' => 'recommended', 'owner_notified_at' => now()->toIso8601String()]]),
            $this->humanAnswer(['metadata' => ['respondent_type' => 'agent']]),
        ] as $echo) {
            (new ReviewHumanAnswer($echo->public_id))->handle(app(ReviewService::class));
        }

        Queue::assertNotPushed(NotifyOwnerOfAnswer::class);
        Http::assertNothingSent();
    }

    public function test_voice_answer_waits_for_transcription(): void
    {
        Queue::fake();
        Http::fake();
        $echo = $this->humanAnswer(['body' => '[Audio perspective awaiting review]',
            'metadata' => ['respondent_type' => 'human', 'review_status' => 'pending', 'media' => ['disk' => 'local', 'path' => 'echo-media/x.mp3']]]);

        (new ReviewHumanAnswer($echo->public_id))->handle(app(ReviewService::class));

        $this->assertNull($echo->fresh()->reviewed_at);
        Queue::assertNotPushed(NotifyOwnerOfAnswer::class);
        Http::assertNothingSent();
    }

    public function test_transcribed_voice_answer_judges_the_words_not_the_placeholder(): void
    {
        Queue::fake();
        $this->fakeVerdict('{"release": true, "reason": "", "confidence": 88, "on_topic": 85, "genuine": 85, "corrected": "I would buy the bakery.", "note": "ok"}');
        $echo = $this->humanAnswer(['body' => '[Audio perspective awaiting review]', 'transcript' => 'i would buy the bakery down the street',
            'metadata' => ['respondent_type' => 'human', 'review_status' => 'pending', 'media' => ['disk' => 'local', 'path' => 'echo-media/x.mp3']]]);

        (new ReviewHumanAnswer($echo->public_id))->handle(app(ReviewService::class));

        $this->assertSame('recommended', $echo->fresh()->metadata['review_status']);
        $this->assertSame('I would buy the bakery.', $echo->fresh()->review_meta['corrected']);
        Queue::assertPushed(NotifyOwnerOfAnswer::class);
    }

    public function test_sweep_never_pings_a_held_answer(): void
    {
        config()->set('tbwim.owner_alerts.bot_token', 'TESTBOT:token');
        config()->set('tbwim.owner_alerts.chat_id', '112233');
        Http::fake();
        Carbon::setTestNow(now()->subMinutes(40));
        $held = $this->humanAnswer(['body' => 'Buy followers fast at example dot com now.', 'reviewed_at' => now(), 'metadata' => ['respondent_type' => 'human', 'review_status' => 'held']]);
        Carbon::setTestNow();

        $this->artisan('tbwim:notify-pending')->assertSuccessful();

        Http::assertNothingSent();
        $this->assertArrayNotHasKey('owner_notified_at', (array) $held->fresh()->metadata);
    }
}
