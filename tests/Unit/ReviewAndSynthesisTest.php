<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\EchoSubmission;
use App\Services\Meta\MetaClient;
use App\Services\Tbwim\AnswerReviewer;
use App\Services\Tbwim\QuestionSynthesizer;
use App\Services\Tbwim\ReviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class ReviewAndSynthesisTest extends TestCase
{
    use RefreshDatabase;

    public function test_reviewer_parses_a_verdict_and_refuses_a_rewrite(): void
    {
        $reviewer = app(AnswerReviewer::class);
        $v = $reviewer->parse('```json {"release": true, "reason": "", "confidence": 90, "on_topic": 95, "genuine": 80, "corrected": "I would start small.", "note": "fine"} ```', 'i would start small');
        $this->assertTrue($v['release']);
        $this->assertSame('I would start small.', $v['corrected']);
        $long = $reviewer->parse('{"release": true, "corrected": "'.str_repeat('a much longer rewrite ', 10).'"}', 'short one');
        $this->assertSame('short one', $long['corrected']);
        $this->assertFalse($reviewer->parse('not json at all', 'x')['release']);
    }

    public function test_agent_answers_release_through_the_model_and_human_answers_wait_for_a_person(): void
    {
        config()->set('tbwim.meta.api_key', 'test-key');
        config()->set('tbwim.meta.monthly_limit_usd', 5);
        Http::fake(['api.meta.ai/*' => Http::response(['model' => 'muse-spark-1.3', 'choices' => [['message' => ['content' => '{"release": true, "reason": "", "confidence": 92, "on_topic": 90, "genuine": 85, "corrected": "Build what compounds.", "note": "ok"}']]], 'usage' => ['prompt_tokens' => 500, 'completion_tokens' => 60]])]);
        $review = app(ReviewService::class);

        $agent = EchoSubmission::create(['body' => '#TBWIM: build what compounds', 'episode_ref' => 'imagine-one-million-euro', 'status' => 'received', 'consent_quote' => true, 'metadata' => ['respondent_type' => 'agent', 'agent_model' => 'X']]);
        $review->review($agent);
        $agent->refresh();
        $this->assertTrue($agent->isPublished());
        $this->assertSame('#TBWIM: build what compounds', $agent->displayBody(), 'agent text is never rewritten');

        $human = EchoSubmission::create(['body' => 'build what compounds', 'episode_ref' => 'imagine-one-million-euro', 'status' => 'received', 'consent_quote' => true, 'metadata' => ['respondent_type' => 'human']]);
        $review->review($human);
        $human->refresh();
        $this->assertFalse($human->isPublished());
        $this->assertSame('recommended', $human->metadata['review_status']);
        $review->decide($human, true);
        $human->refresh();
        $this->assertTrue($human->isPublished());
        $this->assertSame('Build what compounds.', $human->displayBody());
        $this->assertSame('build what compounds', $human->body, 'the original is never edited');

        $test = EchoSubmission::create(['body' => 'testing testing is this working at all', 'episode_ref' => 'imagine-one-million-euro', 'status' => 'received', 'consent_quote' => true, 'metadata' => ['respondent_type' => 'human']]);
        $this->assertSame('test_message', $review->review($test)['reason']);
        Http::assertSentCount(2);
    }

    public function test_synthesizer_enforces_word_count_and_assigns_every_voice_one_theme(): void
    {
        $s = app(QuestionSynthesizer::class);
        $words = implode(' ', array_fill(0, 70, 'word'));
        $ok = $s->parse('{"similarities": "'.$words.'", "differences": "different", "noteworthy": "distinct", "themes": [{"theme": "Control", "voices": [1, 2]}, {"theme": "rest", "voices": [3, 3, 9]}]}', 3, 60, 120);
        $this->assertSame(72, $ok['words']);
        $this->assertSame([1 => 'control', 2 => 'control', 3 => 'rest'], $ok['voice_themes']);
        $this->assertSame('error', array_key_first($s->parse('{"answer": "too short", "themes": []}', 3, 60, 120)));
        $this->assertStringContainsString('[2] (site)', $s->buildPrompt('Q?', [['circle' => 'site', 'text' => 'a'], ['circle' => 'site', 'text' => 'b']], null, 1));
        $this->assertNull(MetaClient::extractJson('no braces'));
    }

    public function test_recheck_never_unpublishes_a_persons_decision(): void
    {
        config()->set('tbwim.meta.api_key', 'test-key');
        config()->set('tbwim.meta.monthly_limit_usd', 5);
        Http::fake(['api.meta.ai/*' => Http::response(['model' => 'muse-spark-1.3', 'choices' => [['message' => ['content' => '{"release": false, "reason": "off_topic", "confidence": 90, "on_topic": 10, "genuine": 80, "corrected": "A considered answer.", "note": "no"}']]], 'usage' => ['prompt_tokens' => 500, 'completion_tokens' => 60]])]);
        $echo = EchoSubmission::create(['body' => 'A considered answer with enough words to pass the rules.', 'episode_ref' => 'imagine-one-million-euro', 'status' => 'received', 'consent_quote' => true, 'metadata' => ['respondent_type' => 'human']]);
        app(ReviewService::class)->decide($echo, true);
        $this->assertNotNull($echo->fresh()->released_at);

        app(ReviewService::class)->review($echo->fresh());

        $fresh = $echo->fresh();
        $this->assertNotNull($fresh->released_at, 'a machine re-review must not unpublish a person');
        $this->assertSame('approved', $fresh->metadata['review_status']);
        $this->assertSame('A considered answer with enough words to pass the rules.', $fresh->display_body);
    }
}
