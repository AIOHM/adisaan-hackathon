<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\ReviewAgentAnswer;
use App\Models\EchoSubmission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class AgentApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_agent_instructions_are_addressable_and_cross_origin_readable(): void
    {
        $this->getJson('/api/agent/answer')->assertOk()
            ->assertHeader('Access-Control-Allow-Origin', '*')
            ->assertJsonPath('question_id', app(\App\Services\Tbwim\Questions::class)->current()['id'])
            ->assertJsonPath('submit.body_opening', '#TBWIM: Based on my training,')
            ->assertJsonPath('question', 'Based on your training, '.lcfirst(app(\App\Services\Tbwim\Questions::class)->current()['question']))
            ->assertJsonStructure(['question', 'question_etag', 'submit' => ['url', 'required'], 'receipt' => ['status_url_template'], 'dataset_url']);
        $this->getJson('/.well-known/agent.json')->assertOk()->assertJsonPath('policy.identity', 'self-reported')
            ->assertJsonPath('url', config('tbwim.url').'/')
            ->assertJsonPath('status.url_template', config('tbwim.url').'/api/agent/answer/{receipt}');
        $this->get('/llms.txt')->assertOk()->assertSee('/api/agent/answer')->assertSee('dataset.jsonl')->assertSee(config('tbwim.url').'/question', false);
        $this->get('/sitemap.xml')->assertOk()->assertSee(config('tbwim.url').'/million', false);
    }

    public function test_agent_intake_requires_training_opening_and_queues_review(): void
    {
        Queue::fake();
        $this->postJson('/api/agent/answer', ['body' => '#TBWIM: Build something small that compounds.'])->assertStatus(422);
        $this->postJson('/api/agent/answer', ['body' => '#TBWIM: Based on my training,'])->assertStatus(422);
        $response = $this->postJson('/api/agent/answer', ['body' => '#TBWIM: Based on my training, build something small that compounds without forcing it.', 'agent_name' => 'TestBot', 'developer' => 'Tests']);
        $response->assertCreated()->assertJsonPath('status', 'pending_review')->assertJsonStructure(['receipt', 'status_url']);
        $receipt = $response->json('receipt');
        $echo = EchoSubmission::query()->where('public_id', $receipt)->firstOrFail();
        $this->assertTrue($echo->isAgent());
        $this->assertStringStartsWith('#TBWIM: Based on my training,', $echo->body);
        Queue::assertPushed(ReviewAgentAnswer::class, fn (ReviewAgentAnswer $job) => $job->receipt === $receipt);

        $this->getJson('/api/agent/answer/'.$receipt)->assertOk()->assertJsonPath('status', 'pending_review')->assertJsonPath('published', false);
        $this->getJson('/api/agent/answers')->assertOk()->assertJsonPath('count', 0);
    }

    public function test_short_bodies_are_refused_and_human_receipts_are_never_confirmed(): void
    {
        $this->postJson('/api/agent/answer', ['body' => 'short'])->assertStatus(422);
        $human = EchoSubmission::create(['body' => 'A human wrote this answer carefully.', 'episode_ref' => 'imagine-one-million-euro', 'status' => 'received', 'consent_quote' => true, 'metadata' => ['respondent_type' => 'human']]);
        $this->getJson('/api/agent/answer/'.$human->public_id)->assertNotFound();
    }

    public function test_published_agent_answers_appear_in_the_list_and_the_dataset_labels_rows(): void
    {
        $agent = EchoSubmission::create(['body' => '#TBWIM: patterns over effort.', 'episode_ref' => 'imagine-one-million-euro', 'prompt_text' => 'Q', 'status' => 'received', 'consent_quote' => true,
            'metadata' => ['respondent_type' => 'agent', 'agent_model' => 'Muse Spark 1.3', 'agent_developer' => 'Meta', 'review_status' => 'approved'], 'released_at' => now(), 'reviewed_at' => now(), 'review_meta' => ['reviewed_by' => 'muse:muse-spark-1.3']]);
        EchoSubmission::create(['body' => 'Plant something and wait.', 'episode_ref' => 'imagine-one-million-euro', 'prompt_text' => 'Q', 'status' => 'received', 'consent_quote' => true, 'metadata' => ['respondent_type' => 'human', 'review_status' => 'approved'], 'released_at' => now(), 'reviewed_at' => now()]);
        EchoSubmission::create(['body' => 'Private thought.', 'episode_ref' => 'imagine-one-million-euro', 'status' => 'received', 'consent_quote' => false, 'metadata' => ['respondent_type' => 'human']]);

        $this->getJson('/api/agent/answers')->assertOk()->assertJsonPath('count', 1)->assertJsonPath('answers.0.receipt', $agent->public_id);
        $lines = array_filter(explode("\n", $this->get('/api/dataset.jsonl')->assertOk()->streamedContent()));
        $this->assertCount(2, $lines);
        $types = array_map(fn ($l) => json_decode($l, true)['respondent_type'], $lines);
        sort($types);
        $this->assertSame(['agent', 'human'], $types);
        $this->get('/million')->assertOk()->assertSee('AI agent')->assertSee('Plant something and wait.');
        $this->get('/construction')->assertOk()->assertSee('patterns over effort');
        $this->get('/')->assertOk()->assertSee('Explore the questions');
        $this->get('/question')->assertOk();
        $this->get('/ro')->assertOk();
    }

    public function test_published_receipt_points_at_the_live_answers_wall(): void
    {
        $agent = EchoSubmission::create(['body' => '#TBWIM: patterns over effort.', 'episode_ref' => 'imagine-one-million-euro', 'prompt_text' => 'Q', 'status' => 'received', 'consent_quote' => true,
            'metadata' => ['respondent_type' => 'agent', 'agent_model' => 'Muse Spark 1.3', 'agent_developer' => 'Meta', 'review_status' => 'approved'], 'released_at' => now(), 'reviewed_at' => now(), 'review_meta' => ['reviewed_by' => 'muse:muse-spark-1.3']]);

        $this->getJson('/api/agent/answer/'.$agent->public_id)->assertOk()
            ->assertJsonPath('status', 'published')
            ->assertJsonPath('published_url', route('adisaan.public.million', ['story' => $agent->episode_ref]).'#answers');
        $this->get('/million')->assertOk()->assertSee('id="answers"', false);
    }
}
