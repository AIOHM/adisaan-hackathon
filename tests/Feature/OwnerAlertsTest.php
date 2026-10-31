<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\NotifyOwnerOfAnswer;
use App\Jobs\NotifyOwnerOfLead;
use App\Models\EchoSubmission;
use App\Services\Tbwim\OwnerAlerts;
use App\Support\FormProofInspector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

final class OwnerAlertsTest extends TestCase
{
    use RefreshDatabase;

    private function ownerConfig(): void
    {
        config()->set('tbwim.owner_alerts.bot_token', 'TESTBOT:token');
        config()->set('tbwim.owner_alerts.chat_id', '112233');
    }

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
            'body' => 'Plant something small and tend it every morning.',
            'episode_ref' => 'imagine-one-million-euro',
            'prompt_text' => 'What is the best way you imagine to make one million euro?',
            'status' => 'received',
            'consent_quote' => true,
            'metadata' => ['respondent_type' => 'human', 'review_status' => 'pending'],
        ], $over));
    }

    public function test_human_intake_queues_an_owner_notification(): void
    {
        $this->ownerConfig();
        Queue::fake();
        $this->postJson('/answer', ['body' => 'Build one thing people need and keep it running quietly.', 'episode_ref' => 'imagine-one-million-euro', 'share_mode' => 'public', 'signature' => 'Ana', '_answer_form_token' => $this->proof()])->assertCreated();
        Queue::assertPushed(NotifyOwnerOfAnswer::class);
    }

    public function test_quarantined_answers_do_not_notify(): void
    {
        $this->ownerConfig();
        Queue::fake();
        $this->postJson('/answer', ['body' => 'see https://a.example and https://b.example now', 'episode_ref' => 'imagine-one-million-euro', 'share_mode' => 'public', '_answer_form_token' => $this->proof()])->assertCreated();
        $this->assertSame('quarantined', EchoSubmission::query()->firstOrFail()->status);
        Queue::assertNotPushed(NotifyOwnerOfAnswer::class);
    }

    public function test_answer_notification_names_the_question_and_links_both_decisions(): void
    {
        $this->ownerConfig();
        Http::fake();
        $echo = $this->humanAnswer();
        (new NotifyOwnerOfAnswer($echo->public_id))->handle(app(OwnerAlerts::class));
        $sent = null;
        Http::assertSent(function ($request) use (&$sent): bool {
            if (! str_ends_with($request->url(), '/botTESTBOT:token/sendMessage')) {
                return false;
            }
            $sent = $request->data();

            return ($request['chat_id'] ?? null) === '112233'
                && str_contains((string) ($request['text'] ?? ''), 'What is the best way you imagine')
                && str_contains((string) ($request['text'] ?? ''), 'Plant something small');
        });
        $buttons = $sent['reply_markup']['inline_keyboard'][0] ?? [];
        $this->assertCount(2, $buttons);
        $this->assertStringContainsString('Release', (string) $buttons[0]['text']);
        $this->assertStringContainsString('Reject', (string) $buttons[1]['text']);
        $this->get($buttons[0]['url'])->assertOk()->assertSee('Plant something small', false);
        $this->get($buttons[1]['url'])->assertOk()->assertSee('Plant something small', false);
    }

    public function test_signed_decide_links_release_or_hold_and_reject_forgeries(): void
    {
        $echo = $this->humanAnswer();
        $approve = URL::temporarySignedRoute('tbwim.owner.decide', now()->addDays(7), ['submission' => $echo->public_id, 'decision' => 'approve']);
        $this->get($approve)->assertOk()->assertSee('Release', false);
        $this->post($approve)->assertOk();
        $fresh = $echo->fresh();
        $this->assertNotNull($fresh->released_at);
        $this->assertSame('approved', $fresh->metadata['review_status']);
        $this->assertSame('human', $fresh->metadata['decided_by']);
        $this->post($approve)->assertOk()->assertSee('already decided', false);
        $this->get($approve.'tampered')->assertForbidden();

        $held = $this->humanAnswer(['body' => 'A second answer that stays back.']);
        $reject = URL::temporarySignedRoute('tbwim.owner.decide', now()->addDays(7), ['submission' => $held->public_id, 'decision' => 'reject']);
        $this->post($reject)->assertOk();
        $this->assertNull($held->fresh()->released_at);
        $this->assertSame('rejected', $held->fresh()->metadata['review_status']);
    }

    public function test_agent_answers_and_private_answers_cannot_be_released_from_a_link(): void
    {
        $agent = $this->humanAnswer(['metadata' => ['respondent_type' => 'agent']]);
        $agentUrl = URL::temporarySignedRoute('tbwim.owner.decide', now()->addDays(7), ['submission' => $agent->public_id, 'decision' => 'approve']);
        $this->get($agentUrl)->assertNotFound();

        $private = $this->humanAnswer(['body' => 'A private answer.', 'consent_quote' => false]);
        $privateUrl = URL::temporarySignedRoute('tbwim.owner.decide', now()->addDays(7), ['submission' => $private->public_id, 'decision' => 'approve']);
        $this->post($privateUrl)->assertOk()->assertSee('not eligible', false);
        $this->assertNull($private->fresh()->released_at);
    }

    public function test_join_lead_notifies_once_per_email(): void
    {
        $this->ownerConfig();
        Http::fake();
        Queue::fake();
        $this->post('/', ['email' => 'newcomer@example.com', 'consent_updates' => true])->assertRedirect();
        Queue::assertPushed(NotifyOwnerOfLead::class);
        (new NotifyOwnerOfLead('newcomer@example.com'))->handle(app(OwnerAlerts::class));
        Http::assertSent(fn ($request): bool => str_ends_with($request->url(), '/botTESTBOT:token/sendMessage')
            && str_contains((string) ($request['text'] ?? ''), 'newcomer@example.com'));

        Queue::fake();
        Http::fake();
        $this->post('/', ['email' => 'newcomer@example.com', 'consent_updates' => true])->assertRedirect();
        Queue::assertNotPushed(NotifyOwnerOfLead::class);
        Http::assertNothingSent();
    }

    public function test_notify_pending_sweep_covers_what_the_queue_missed(): void
    {
        $this->ownerConfig();
        Http::fake();
        Carbon::setTestNow(now()->subMinutes(20));
        $missed = $this->humanAnswer(['body' => 'An answer the queue never announced.']);
        $junk = $this->humanAnswer(['body' => 'see https://a.example and https://b.example now', 'status' => 'quarantined']);
        Carbon::setTestNow();
        $this->artisan('tbwim:notify-pending')->assertSuccessful();
        Http::assertSentCount(1);
        $this->assertNotNull($missed->fresh()->metadata['owner_notified_at']);
        $this->assertArrayNotHasKey('owner_notified_at', (array) $junk->fresh()->metadata);
    }

    public function test_decided_rows_are_never_pinged(): void
    {
        $this->ownerConfig();
        Http::fake();
        $echo = $this->humanAnswer(['metadata' => ['respondent_type' => 'human', 'review_status' => 'approved', 'decided_by' => 'human']]);
        (new NotifyOwnerOfAnswer($echo->public_id))->handle(app(OwnerAlerts::class));
        Http::assertNothingSent();

        Carbon::setTestNow(now()->subMinutes(20));
        $old = $this->humanAnswer(['body' => 'Decided long ago, never announced.', 'metadata' => ['respondent_type' => 'human', 'review_status' => 'approved', 'decided_by' => 'human']]);
        Carbon::setTestNow();
        $this->artisan('tbwim:notify-pending')->assertSuccessful();
        Http::assertNothingSent();
        $this->assertArrayNotHasKey('owner_notified_at', (array) $old->fresh()->metadata);
    }
}
