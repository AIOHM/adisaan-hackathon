<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AnswerVote;
use App\Models\EchoSubmission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AnswerVoteTest extends TestCase
{
    use RefreshDatabase;

    private function releasedHuman(array $over = []): EchoSubmission
    {
        return EchoSubmission::create(array_merge([
            'body' => 'Plant something small and tend it every morning without fail.',
            'episode_ref' => 'imagine-one-million-euro',
            'prompt_text' => 'Q',
            'status' => 'received',
            'consent_quote' => true,
            'released_at' => now(),
            'reviewed_at' => now(),
            'metadata' => ['respondent_type' => 'human', 'review_status' => 'approved'],
        ], $over));
    }

    public function test_anonymous_vote_counts_and_returns_to_the_card(): void
    {
        $echo = $this->releasedHuman();
        $back = route('adisaan.public.million', ['story' => $echo->episode_ref]).'#answers';

        $this->post('/answer', ['vote_echo_id' => $echo->id, 'redirect_to' => $back])
            ->assertRedirect(route('adisaan.public.million', ['story' => $echo->episode_ref]).'#answer-'.$echo->id);
        $this->assertSame(1, AnswerVote::query()->where('echo_submission_id', $echo->id)->count());
        $this->assertContains($echo->id, session('adisaan_voted_echoes'));

        $this->get('/million?story='.$echo->episode_ref)->assertOk()
            ->assertSee('is-voted', false)
            ->assertSee('>1<', false);
    }

    public function test_repeat_vote_from_the_same_visitor_counts_once(): void
    {
        $echo = $this->releasedHuman();

        $this->post('/answer', ['vote_echo_id' => $echo->id])->assertRedirect();
        $this->post('/answer', ['vote_echo_id' => $echo->id])->assertRedirect();
        $this->assertSame(1, AnswerVote::query()->where('echo_submission_id', $echo->id)->count());
    }

    public function test_votes_on_hidden_or_missing_answers_404(): void
    {
        $hidden = $this->releasedHuman(['released_at' => null]);

        $this->post('/answer', ['vote_echo_id' => $hidden->id])->assertNotFound();
        $this->post('/answer', ['vote_echo_id' => 999999])->assertNotFound();
        $this->assertSame(0, AnswerVote::query()->count());
    }

    public function test_external_return_address_falls_back_to_the_wall(): void
    {
        $echo = $this->releasedHuman();

        $this->post('/answer', ['vote_echo_id' => $echo->id, 'redirect_to' => 'https://evil.example/steal'])
            ->assertRedirect(route('adisaan.public.million', ['story' => $echo->episode_ref]).'#answer-'.$echo->id);
        $this->assertSame(1, AnswerVote::query()->count());
    }

    public function test_json_vote_returns_the_count(): void
    {
        $echo = $this->releasedHuman();

        $this->postJson('/answer', ['vote_echo_id' => $echo->id])
            ->assertOk()->assertJson(['ok' => true, 'votes' => 1]);
    }

    public function test_wall_shows_a_working_vote_button_without_lock(): void
    {
        $echo = $this->releasedHuman();

        $this->get('/million?story='.$echo->episode_ref)->assertOk()
            ->assertSee('data-vote-form', false)
            ->assertDontSee('unlock-gate', false)
            ->assertDontSee('Members can vote', false);
    }
}
