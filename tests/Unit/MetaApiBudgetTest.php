<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Meta\MetaApiBudget;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

final class MetaApiBudgetTest extends TestCase
{
    use RefreshDatabase;

    private function request(array $overrides = []): Request
    {
        return new Request('POST', 'https://api.meta.ai/v1/chat/completions', [], json_encode(array_replace(['model' => 'muse-spark-1.3', 'max_tokens' => 40, 'messages' => [['role' => 'user', 'content' => 'Test']]], $overrides)));
    }

    public function test_reservations_and_settlement(): void
    {
        config()->set('observatory.meta.monthly_limit_usd', 2);
        $this->assertSame(1400000, MetaApiBudget::reservationFor('muse-spark-1.3'));
        $this->assertSame(4600000, MetaApiBudget::reservationFor('muse-wonder-1.0'));
        $this->assertSame(30000, MetaApiBudget::reservationFor('muse-image-1.0', 3));
        $budget = new MetaApiBudget;
        $handler = $budget->middleware()(fn () => Create::promiseFor(new Response(200, [], json_encode(['usage' => ['prompt_tokens' => 1000, 'completion_tokens' => 1000]]))));
        $handler($this->request(), [])->wait();
        $this->assertSame(0.0055, $budget->status()['committed_usd']);
        $this->assertSame(1, $budget->status()['requests']);

        // Wonder does not fit in the remaining $2 limit and never reaches the network.
        $calls = 0;
        $handler = $budget->middleware()(function () use (&$calls) {
            $calls++;

            return Create::promiseFor(new Response(200, [], '{}'));
        });
        try {
            $handler($this->request(['model' => 'muse-wonder-1.0']), [])->wait();
            $this->fail('Over-limit request was sent');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('4.60', $e->getMessage());
        }
        $this->assertSame(0, $calls);
    }

    public function test_failures_keep_the_reservation_and_a_rejected_key_releases_it(): void
    {
        config()->set('observatory.meta.monthly_limit_usd', 5);
        $budget = new MetaApiBudget;
        $handler = $budget->middleware()(fn () => Create::rejectionFor(new RuntimeException('timeout')));
        try {
            $handler($this->request(), [])->wait();
        } catch (RuntimeException) {
        }
        $this->assertSame(1.4, $budget->status()['committed_usd']);
        $handler = $budget->middleware()(fn () => Create::promiseFor(new Response(401, [], json_encode(['error' => ['code' => 'invalid_api_key']]))));
        $handler($this->request(), [])->wait();
        $this->assertSame(1.4, $budget->status()['committed_usd']);
    }

    public function test_unsafe_requests_never_reach_the_network(): void
    {
        $budget = new MetaApiBudget;
        $refused = 0;
        foreach ([['model' => 'muse-spark-1.3-contributor'], ['model' => 'muse-wonder'], ['max_tokens' => 20000], ['tools' => [['type' => 'web_search']]], ['messages' => [['role' => 'user', 'content' => [['type' => 'image_url']]]]]] as $invalid) {
            try {
                $budget->validateRequest($this->request($invalid), json_decode((string) $this->request($invalid)->getBody(), true));
                $this->fail('Unsafe request accepted: '.json_encode($invalid));
            } catch (RuntimeException) {
                $refused++;
            }
        }
        $this->assertSame(5, $refused);
    }
}
