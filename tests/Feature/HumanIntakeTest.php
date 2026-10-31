<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\EchoSubmission;
use App\Services\Tbwim\ReviewService;
use App\Support\FormProofInspector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

final class HumanIntakeTest extends TestCase
{
    use RefreshDatabase;

    private function proof(): string
    {
        Carbon::setTestNow(now()->subSeconds(10));
        $token = app(FormProofInspector::class)->issueToken('tbwim_answer');
        Carbon::setTestNow();

        return $token;
    }

    public function test_a_public_answer_is_stored_with_consent_and_without_raw_ip(): void
    {
        $this->get('/answer')->assertOk()->assertSee('_answer_form_token', false)->assertDontSee('name="signature"', false);
        $this->post('/answer', ['body' => 'Build one thing people need and keep it running quietly.', 'episode_ref' => 'imagine-one-million-euro', 'share_mode' => 'public', 'signature' => 'Ana', '_answer_form_token' => $this->proof()])
            ->assertRedirect(route('adisaan.public.received'));
        $echo = EchoSubmission::query()->firstOrFail();
        $this->assertTrue($echo->consent_quote);
        $this->assertSame('Ana', $echo->signature);
        $this->assertNull($echo->released_at);
        $this->assertSame('human', $echo->metadata['respondent_type']);
        $this->assertNotNull($echo->client_ip_hash);
        $this->assertStringNotContainsString('127.0.0.1', json_encode($echo->toArray()));
        $this->get('/about')->assertOk()->assertDontSee('keep it running quietly');
    }

    public function test_missing_proof_and_honeypot_are_refused_and_links_are_quarantined(): void
    {
        $this->postJson('/answer', ['body' => 'A real answer without the proof token.', 'episode_ref' => 'imagine-one-million-euro'])->assertStatus(422);
        $this->postJson('/answer', ['body' => 'A real answer with the honeypot filled.', 'episode_ref' => 'imagine-one-million-euro', '_answer_form_token' => $this->proof(), '_website_url' => 'x'])->assertStatus(422);
        $this->postJson('/answer', ['body' => 'see https://a.example and https://b.example now', 'episode_ref' => 'imagine-one-million-euro', '_answer_form_token' => $this->proof(), 'share_mode' => 'public'])->assertCreated();
        $this->assertSame('quarantined', EchoSubmission::query()->firstOrFail()->status);
        $this->postJson('/answer', ['body' => 'An answer to a question that is not open yet.', 'episode_ref' => 'know-when-to-stop', '_answer_form_token' => $this->proof()])->assertStatus(422);
    }

    public function test_second_question_form_supplies_a_valid_prompt_key(): void
    {
        $this->travelTo(Carbon::parse('2026-10-30 12:00:00', 'Europe/Bucharest'));
        $html = $this->get('/answer?story=start-when-not-ready')->assertOk()->getContent();
        $this->assertSame(1, preg_match('/name="prompt_key" value="([^"]+)"/', $html, $match));
        $this->assertSame('what-is-the-best-way-you-imagine-to-start-something-when-you-dont-feel-ready', $match[1]);
        $this->assertStringContainsString('"prompt_key": "'.$match[1].'"', $html);
        Carbon::setTestNow(Carbon::parse('2026-10-30 11:59:50', 'Europe/Bucharest'));
        $proof = app(FormProofInspector::class)->issueToken('tbwim_answer');
        Carbon::setTestNow(Carbon::parse('2026-10-30 12:00:00', 'Europe/Bucharest'));
        $this->post('/answer', ['body' => 'When not ready, I wait for the current.', 'episode_ref' => 'start-when-not-ready', 'prompt_key' => $match[1], 'share_mode' => 'public', 'signature' => 'AdiSaan', '_answer_form_token' => $proof])->assertRedirect(route('adisaan.public.received'));
        $this->assertSame('AdiSaan', EchoSubmission::query()->firstOrFail()->signature);
        $this->assertSame($match[1], EchoSubmission::query()->firstOrFail()->prompt_key);
    }

    public function test_member_answers_require_explicit_consent_human_approval_and_bridge_authentication(): void
    {
        config()->set('tbwim.member_bridge_secret', str_repeat('a', 48));
        $this->postJson('/answer', ['body' => 'An answer intended only for signed in members.', 'episode_ref' => 'imagine-one-million-euro', 'share_mode' => 'members', '_answer_form_token' => $this->proof()])->assertCreated();
        $member = EchoSubmission::query()->firstOrFail();
        $this->assertFalse($member->consent_quote);
        $this->assertSame('sender_selected_members_only', $member->metadata['member_sharing']['basis']);
        $this->getJson('/api/internal/member-answers?question=imagine-one-million-euro')->assertNotFound();
        $this->withToken(str_repeat('a', 48))->getJson('/api/internal/member-answers?question=imagine-one-million-euro')->assertJsonCount(0, 'answers');
        app(ReviewService::class)->decide($member, true);
        EchoSubmission::create(['body' => 'An old private answer must remain private.', 'episode_ref' => 'imagine-one-million-euro', 'status' => 'received', 'consent_quote' => false, 'released_at' => now(), 'reviewed_at' => now(), 'metadata' => ['review_status' => 'approved', 'decided_by' => 'human']]);
        $this->withToken(str_repeat('a', 48))->getJson('/api/internal/member-answers?question=imagine-one-million-euro')->assertJsonCount(1, 'answers')->assertJsonPath('answers.0.body', 'An answer intended only for signed in members.');
        $this->get('/arena?story=imagine-one-million-euro')->assertDontSee('An answer intended only for signed in members.');
        $this->get('/api/dataset.jsonl')->assertDontSee('An answer intended only for signed in members.');
    }
}
