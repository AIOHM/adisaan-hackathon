<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\EchoSubmission;
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
        $this->get('/answer')->assertOk()->assertSee('_answer_form_token', false);
        $this->post('/answer', ['body' => 'Build one thing people need and keep it running quietly.', 'episode_ref' => 'imagine-one-million-euro', 'share_mode' => 'public', 'signature' => 'Ana', '_answer_form_token' => $this->proof()])
            ->assertRedirect(route('adisaan.public.received'));
        $echo = EchoSubmission::query()->firstOrFail();
        $this->assertTrue($echo->consent_quote);
        $this->assertNull($echo->released_at);
        $this->assertSame('human', $echo->metadata['respondent_type']);
        $this->assertNotNull($echo->client_ip_hash);
        $this->assertStringNotContainsString('127.0.0.1', json_encode($echo->toArray()));
        $this->get('/construction')->assertOk()->assertDontSee('keep it running quietly');
    }

    public function test_missing_proof_and_honeypot_are_refused_and_links_are_quarantined(): void
    {
        $this->postJson('/answer', ['body' => 'A real answer without the proof token.', 'episode_ref' => 'imagine-one-million-euro'])->assertStatus(422);
        $this->postJson('/answer', ['body' => 'A real answer with the honeypot filled.', 'episode_ref' => 'imagine-one-million-euro', '_answer_form_token' => $this->proof(), '_website_url' => 'x'])->assertStatus(422);
        $this->postJson('/answer', ['body' => 'see https://a.example and https://b.example now', 'episode_ref' => 'imagine-one-million-euro', '_answer_form_token' => $this->proof(), 'share_mode' => 'public'])->assertCreated();
        $this->assertSame('quarantined', EchoSubmission::query()->firstOrFail()->status);
        $this->postJson('/answer', ['body' => 'An answer to a question that is not open yet.', 'episode_ref' => 'know-when-to-stop', '_answer_form_token' => $this->proof()])->assertStatus(422);
    }
}
