<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AnswerVote;
use App\Models\EchoSubmission;
use App\Services\Tbwim\ReviewService;
use App\Support\FormProofInspector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cookie;
use Tests\TestCase;

final class MemberUnlockTest extends TestCase
{
    use RefreshDatabase;

    private function proof(): string
    {
        Carbon::setTestNow(now()->subSeconds(10));
        $token = app(FormProofInspector::class)->issueToken('tbwim_answer');
        Carbon::setTestNow();

        return $token;
    }

    private function releaseMembersAnswer(): EchoSubmission
    {
        config()->set('tbwim.member_bridge_secret', str_repeat('a', 48));
        $this->postJson('/answer', ['body' => 'A members-only perspective worth unlocking for.', 'episode_ref' => 'imagine-one-million-euro', 'share_mode' => 'members', 'signature' => 'AdiSaan', '_answer_form_token' => $this->proof()])->assertCreated();
        $member = EchoSubmission::query()->firstOrFail();
        app(ReviewService::class)->decide($member, true);

        return $member->refresh();
    }

    public function test_guests_see_members_answers_blurred_inside_the_people_column(): void
    {
        $this->releaseMembersAnswer();

        $html = $this->get('/')->assertOk()
            ->assertSee('AdiSaan', false)
            ->assertSee('worth unlocking for')
            ->assertSee('wall__blur', false)
            ->assertSee('Answer a question to reveal', false)
            ->assertSee('wall__card--locked', false)
            ->assertSee('1 members-only', false)
            ->getContent();
        $this->assertLessThan(
            (int) strpos($html, 'From agents'),
            (int) strpos($html, 'wall__card--locked'),
            'The locked card sits in the people column, before the agents column.'
        );
    }

    public function test_plain_private_answers_stay_hidden_even_for_unlocked_members(): void
    {
        $this->releaseMembersAnswer();
        EchoSubmission::create(['body' => 'A strictly private line nobody else may read.', 'episode_ref' => 'imagine-one-million-euro', 'status' => 'received', 'consent_quote' => false, 'released_at' => now(), 'reviewed_at' => now(), 'metadata' => ['review_status' => 'approved', 'decided_by' => 'human']]);

        $post = $this->post('/answer', ['body' => 'Build one thing people need and keep it running quietly.', 'episode_ref' => 'imagine-one-million-euro', 'share_mode' => 'public', 'signature' => 'Ana', '_answer_form_token' => $this->proof()])->assertRedirect(route('adisaan.public.received'));
        $raw = $post->getCookie('tbwim_member')?->getValue();
        $this->assertNotSame(null, $raw);

        $this->withCookie('tbwim_member', (string) $raw)->get('/')
            ->assertSee('worth unlocking for')
            ->assertDontSee('wall__blur', false)
            ->assertDontSee('strictly private line');
    }

    public function test_submitting_an_answer_unlocks_and_celebrates_on_the_receipt(): void
    {
        $this->releaseMembersAnswer();

        $post = $this->post('/answer', ['body' => 'Build one thing people need and keep it running quietly.', 'episode_ref' => 'imagine-one-million-euro', 'share_mode' => 'public', 'signature' => 'Ana', '_answer_form_token' => $this->proof()])
            ->assertRedirect(route('adisaan.public.received'))
            ->assertCookie('tbwim_member');

        $raw = $post->getCookie('tbwim_member')?->getValue();
        $this->get('/answer/received')
            ->assertSee('Now you have access to members&rsquo; answers', false)
            ->assertSee('received-celebration', false);
        $this->withCookie('tbwim_member', (string) $raw)->get('/')->assertSee('worth unlocking for');
    }

    public function test_tampered_and_missing_unlock_cookies_stay_locked(): void
    {
        $this->releaseMembersAnswer();

        $this->withCookie('tbwim_member', 'forged.receipt.signature')->get('/')
            ->assertSee('wall__blur', false)
            ->assertSee('Answer a question to reveal', false);
    }

    public function test_quarantined_submissions_do_not_unlock(): void
    {
        $this->releaseMembersAnswer();
        Cookie::flushQueuedCookies();

        $this->post('/answer', ['body' => 'see https://a.example and https://b.example now', 'episode_ref' => 'imagine-one-million-euro', 'share_mode' => 'public', '_answer_form_token' => $this->proof()])
            ->assertRedirect(route('adisaan.public.received'))
            ->assertCookieMissing('tbwim_member');
        $this->assertSame('quarantined', EchoSubmission::query()->where('body', 'like', '%a.example%')->firstOrFail()->status);
    }

    public function test_members_votes_count_on_members_answers_but_guests_get_nothing(): void
    {
        $member = $this->releaseMembersAnswer();
        $post = $this->post('/answer', ['body' => 'Build one thing people need and keep it running quietly.', 'episode_ref' => 'imagine-one-million-euro', 'share_mode' => 'public', 'signature' => 'Ana', '_answer_form_token' => $this->proof()])->assertRedirect(route('adisaan.public.received'));
        $raw = (string) $post->getCookie('tbwim_member')?->getValue();

        $this->post('/answer', ['vote_echo_id' => $member->id])->assertNotFound();
        $this->withCookie('tbwim_member', $raw)->post('/answer', ['vote_echo_id' => $member->id, 'redirect_to' => '/#members-answers'])->assertRedirect('/#answer-'.$member->id);
        $this->assertSame(1, AnswerVote::query()->where('echo_submission_id', $member->id)->count());
    }
}
