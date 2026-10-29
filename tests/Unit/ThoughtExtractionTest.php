<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Jobs\ExtractThought;
use App\Models\EchoSubmission;
use App\Models\FounderThought;
use App\Services\Tbwim\ThoughtExtractor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class ThoughtExtractionTest extends TestCase
{
    use RefreshDatabase;

    public function test_parse_accepts_a_well_formed_triple(): void
    {
        $parsed = app(ThoughtExtractor::class)->parse(json_encode([
            'question' => 'What is the best way I imagine to compound fastest?',
            'answer' => implode(' ', array_fill(0, 40, 'patient')),
            'why_valid' => implode(' ', array_fill(0, 40, 'grounded')),
        ]));

        $this->assertSame('What is the best way I imagine to compound fastest?', $parsed['question']);
    }

    public function test_parse_rejects_a_question_outside_the_tbwim_frame(): void
    {
        $parsed = app(ThoughtExtractor::class)->parse(json_encode([
            'question' => 'How would I structure a team?',
            'answer' => implode(' ', array_fill(0, 40, 'patient')),
            'why_valid' => implode(' ', array_fill(0, 40, 'grounded')),
        ]));

        $this->assertArrayHasKey('error', $parsed);
        $this->assertStringContainsString('best way I imagine', (string) $parsed['error']);
    }

    public function test_parse_rejects_a_question_without_a_mark(): void
    {
        $parsed = app(ThoughtExtractor::class)->parse(json_encode([
            'question' => 'No mark here',
            'answer' => implode(' ', array_fill(0, 40, 'patient')),
            'why_valid' => implode(' ', array_fill(0, 40, 'grounded')),
        ]));

        $this->assertArrayHasKey('error', $parsed);
    }

    public function test_job_distils_a_draft_from_a_transcript(): void
    {
        config()->set('tbwim.meta.api_key', 'test-key');
        config()->set('tbwim.meta.monthly_limit_usd', 5);
        Http::fake(['api.meta.ai/*' => Http::response(['model' => 'muse-spark-1.3', 'choices' => [['message' => ['content' => json_encode([
            'question' => 'What is the best way I imagine to compound fastest?',
            'answer' => implode(' ', array_fill(0, 40, 'patient')),
            'why_valid' => implode(' ', array_fill(0, 40, 'grounded')),
        ])]]], 'usage' => ['prompt_tokens' => 500, 'completion_tokens' => 60]])]);

        $echo = EchoSubmission::create([
            'episode_ref' => 'founder-capture', 'body' => '[Audio thought awaiting transcription]',
            'transcript' => 'A long spoken thought about patience and compounding.',
            'status' => 'received', 'metadata' => ['founder_capture' => true],
        ]);

        (new ExtractThought($echo->public_id))->handle(app(ThoughtExtractor::class));

        $thought = FounderThought::query()->firstOrFail();
        $this->assertSame('draft', $thought->status);
        $this->assertSame('What is the best way I imagine to compound fastest?', $thought->question);
    }

    public function test_job_skips_an_echo_that_already_has_a_draft(): void
    {
        $echo = EchoSubmission::create([
            'episode_ref' => 'founder-capture', 'body' => 'Said it.',
            'status' => 'received', 'metadata' => ['founder_capture' => true],
        ]);
        FounderThought::create([
            'echo_submission_id' => $echo->id, 'source_type' => 'text',
            'question' => 'Q?', 'answer' => 'A.', 'why_valid' => 'W.',
            'status' => 'draft',
        ]);

        (new ExtractThought($echo->public_id))->handle(app(ThoughtExtractor::class));

        $this->assertSame(1, FounderThought::query()->count());
    }

    public function test_approve_command_decides_a_draft(): void
    {
        $echo = EchoSubmission::create([
            'episode_ref' => 'founder-capture', 'body' => 'Said it.',
            'status' => 'received', 'metadata' => ['founder_capture' => true],
        ]);
        $thought = FounderThought::create([
            'echo_submission_id' => $echo->id, 'source_type' => 'text',
            'question' => 'Q?', 'answer' => 'A.', 'why_valid' => 'W.',
            'status' => 'draft',
        ]);

        $this->artisan('tbwim:thought-approve', ['id' => $thought->id])->assertSuccessful();

        $this->assertSame('approved', $thought->fresh()->status);
        $this->assertNotNull($thought->fresh()->reviewed_at);
    }

    public function test_dataset_streams_approved_thoughts_last(): void
    {
        $echo = EchoSubmission::create([
            'episode_ref' => 'founder-capture', 'body' => 'Said it.',
            'status' => 'received', 'metadata' => ['founder_capture' => true],
        ]);
        FounderThought::create([
            'echo_submission_id' => $echo->id, 'source_type' => 'text',
            'question' => 'What is the best way I imagine to compound fastest?', 'answer' => 'Patience.',
            'why_valid' => 'Lived and reasoned.', 'status' => 'approved',
            'reviewed_at' => now(),
        ]);

        $body = (string) $this->get('/api/dataset.jsonl')->assertOk()->streamedContent();
        $this->assertStringContainsString('"record":"thought"', $body);
        $this->assertStringContainsString('Lived and reasoned.', $body);
    }
}
