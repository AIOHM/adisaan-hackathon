<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\ExtractThought;
use App\Models\EchoSubmission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class CaptureIntakeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('tbwim.capture.intake_secret', 'test-secret');
        config()->set('tbwim.capture.founder_ids', ['123']);
        Queue::fake([ExtractThought::class]);
    }

    public function test_capture_rejects_a_bad_secret(): void
    {
        $this->postJson('/api/capture/thought', ['text' => 'hello', 'from_id' => '123', 'message_id' => '1'], ['Authorization' => 'Bearer wrong'])
            ->assertUnauthorized();
    }

    public function test_capture_rejects_a_non_founder(): void
    {
        $this->postJson('/api/capture/thought', ['text' => 'hello', 'from_id' => '999', 'message_id' => '1'], $this->auth())
            ->assertForbidden();
    }

    public function test_capture_needs_text_or_a_file(): void
    {
        $this->postJson('/api/capture/thought', ['from_id' => '123', 'message_id' => '1'], $this->auth())
            ->assertStatus(422);
    }

    public function test_text_capture_stores_a_private_founder_echo_and_queues_extraction(): void
    {
        $response = $this->postJson('/api/capture/thought', [
            'text' => 'A thought about compounding small wins.',
            'from_id' => '123', 'message_id' => '42',
        ], $this->auth());

        $response->assertCreated()->assertJsonPath('ok', true)->assertJsonStructure(['receipt']);
        $echo = EchoSubmission::query()->where('public_id', $response->json('receipt'))->firstOrFail();
        $this->assertFalse($echo->consent_quote);
        $this->assertTrue((bool) ($echo->metadata['founder_capture'] ?? false));
        $this->assertNotNull($echo->reviewed_at);
        Queue::assertPushed(ExtractThought::class);
    }

    public function test_repeated_delivery_returns_the_same_receipt(): void
    {
        $payload = ['text' => 'Say it once.', 'from_id' => '123', 'message_id' => '7'];
        $first = $this->postJson('/api/capture/thought', $payload, $this->auth())->assertCreated();
        $second = $this->postJson('/api/capture/thought', $payload, $this->auth())->assertOk();
        $this->assertSame($first->json('receipt'), $second->json('receipt'));
        $this->assertSame(1, EchoSubmission::query()->count());
    }

    /** @return array<string, string> */
    private function auth(): array
    {
        return ['Authorization' => 'Bearer test-secret'];
    }
}
