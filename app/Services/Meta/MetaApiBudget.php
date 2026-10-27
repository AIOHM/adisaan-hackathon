<?php

declare(strict_types=1);

namespace App\Services\Meta;

use GuzzleHttp\Psr7\Utils;
use Illuminate\Support\Facades\DB;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

/**
 * Durable admission control for every Meta Model API call, in integer
 * millionths of USD. Each request reserves its worst case before it is
 * sent and releases what reported usage did not consume. Streams, network
 * failures and missing usage keep the reservation: the ledger is
 * conservative, never an invoice.
 */
final class MetaApiBudget
{
    /** Standard-mode prices in hundredths of a micro-USD per token (USD per million × 100). */
    public const PRICES = [
        'muse-spark-1.3' => [125, 425],
        'muse-spark-1.2' => [125, 425],
        'muse-spark-1.1' => [125, 425],
        'muse-wonder-1.0' => [400, 2000],
    ];

    /** Flat per-image price in micro-USD ($0.01). */
    public const IMAGE_MODELS = ['muse-image-1.0' => 10000];

    /** Voice transcription in micro-USD per audio second ($0.18/hour, whole seconds). */
    public const AUDIO_MODELS = ['muse-voice-transcribe-1.0' => 50];

    public const CHAT_PATH = '/v1/chat/completions';

    public const IMAGE_PATH = '/v1/images/generations';

    /** The API rejects clips over 10 minutes; the reservation assumes the max. */
    public const MAX_AUDIO_SECONDS = 600;

    public const MAX_INPUT_TOKENS = 1048576;

    public const MAX_OUTPUT_TOKENS = 16384;

    public const MAX_IMAGES_PER_REQUEST = 10;

    public static function reservationFor(string $model, int $images = 1): int
    {
        if (isset(self::IMAGE_MODELS[$model])) {
            return self::IMAGE_MODELS[$model] * max(1, min(self::MAX_IMAGES_PER_REQUEST, $images));
        }
        [$input, $output] = self::PRICES[$model] ?? self::PRICES['muse-spark-1.3'];
        $raw = (self::MAX_INPUT_TOKENS * $input + self::MAX_OUTPUT_TOKENS * $output) / 100;

        return (int) (ceil($raw / 100000) * 100000);
    }

    public static function costFor(string $model, int $input, int $output): int
    {
        [$inputPrice, $outputPrice] = self::PRICES[$model] ?? self::PRICES['muse-spark-1.3'];

        return (int) ceil(($input * $inputPrice + $output * $outputPrice) / 100);
    }

    public static function reservationForAudio(string $model = 'muse-voice-transcribe-1.0'): int
    {
        return self::MAX_AUDIO_SECONDS * (self::AUDIO_MODELS[$model] ?? 50);
    }

    /** Meta bills whole seconds, rounded down; the API caps clips at 10 minutes. */
    public static function costForAudio(int $audioMs): int
    {
        $seconds = max(0, min(self::MAX_AUDIO_SECONDS, (int) floor(max(0, $audioMs) / 1000)));

        return $seconds * (self::AUDIO_MODELS['muse-voice-transcribe-1.0'] ?? 50);
    }

    public function middleware(): callable
    {
        return function (callable $handler): callable {
            return function (RequestInterface $request, array $options) use ($handler) {
                $data = json_decode((string) $request->getBody(), true);
                $isImage = $request->getUri()->getPath() === self::IMAGE_PATH;
                if (is_array($data) && ! $isImage && ! isset($data['max_tokens']) && ! isset($data['max_completion_tokens'])) {
                    $data['max_tokens'] = self::MAX_OUTPUT_TOKENS;
                    $request = $request->withBody(Utils::streamFor(json_encode($data)));
                }
                $this->validateRequest($request, $data);
                $limit = (int) round((float) config('tbwim.meta.monthly_limit_usd', 0) * 1000000);
                $model = (string) $data['model'];
                $reservation = self::reservationFor($model, (int) ($data['n'] ?? 1));
                $period = $this->reserve($limit, $reservation);
                $options['allow_redirects'] = false;

                return $handler($request, $options)->then(function (ResponseInterface $response) use ($period, $data, $model, $reservation, $isImage) {
                    if (! $response->getBody()->isSeekable()) {
                        return $response;
                    }
                    $body = $response->getBody();
                    $position = $body->tell();
                    $payload = json_decode((string) $body, true);
                    $body->seek($position);
                    if (! ($data['stream'] ?? false) && $response->getStatusCode() === 200) {
                        $isImage
                            ? $this->settleImages($period, $payload['data'] ?? null, $reservation, $model)
                            : $this->settle($period, $payload['usage'] ?? [], $reservation, $model);
                    } elseif ($response->getStatusCode() === 401) {
                        // A rejected credential is refused before any token is processed.
                        $this->release($period, $reservation);
                    }

                    return $response;
                });
            };
        };
    }

    public function validateRequest(RequestInterface $request, mixed $data): void
    {
        if (now('UTC')->greaterThan(config('tbwim.meta.pricing_review_before').' 00:00:00')) {
            throw new RuntimeException('Meta API pricing needs review before spending can resume (tbwim.meta.pricing_review_before).');
        }
        $path = $request->getUri()->getPath();
        if ($request->getUri()->getScheme() !== 'https' || $request->getUri()->getHost() !== 'api.meta.ai'
            || ! is_array($data) || ! is_string($data['model'] ?? null)) {
            throw new RuntimeException('Spending-protected Meta API accepts api.meta.ai chat and image requests only.');
        }
        if ($path === self::IMAGE_PATH) {
            $count = $data['n'] ?? 1;
            if (! array_key_exists($data['model'], self::IMAGE_MODELS) || ! is_int($count) || $count < 1 || $count > self::MAX_IMAGES_PER_REQUEST
                || ! empty($data['stream']) || ($data['response_format'] ?? 'b64_json') !== 'b64_json' || trim((string) ($data['prompt'] ?? '')) === '') {
                throw new RuntimeException('Spending-protected Meta image requests: muse-image-1.0, 1 to 10 base64 images, no streaming.');
            }

            return;
        }
        if ($path !== self::CHAT_PATH || ! array_key_exists($data['model'], self::PRICES)
            || ($data['n'] ?? 1) !== 1 || ! empty($data['background']) || ! empty($data['web_search_options'])) {
            throw new RuntimeException('Spending-protected Meta API supports Standard-mode Muse Spark 1.1–1.3 and Muse Wonder 1.0 chat requests only.');
        }
        $maximum = $data['max_completion_tokens'] ?? $data['max_tokens'] ?? null;
        if (! is_int($maximum) || $maximum < 1 || $maximum > self::MAX_OUTPUT_TOKENS) {
            throw new RuntimeException('Meta API requires an output limit between 1 and '.self::MAX_OUTPUT_TOKENS.' tokens.');
        }
        foreach ($data['tools'] ?? [] as $tool) {
            if (($tool['type'] ?? '') !== 'function') {
                throw new RuntimeException('Paid server tools are not supported by the spending cap.');
            }
        }
        foreach ($data['messages'] ?? [] as $message) {
            if (isset($message['content']) && ! is_string($message['content'])) {
                foreach (is_array($message['content']) ? $message['content'] : [[]] as $part) {
                    if (($part['type'] ?? '') !== 'text') {
                        throw new RuntimeException('Spending-protected Meta API accepts text messages only.');
                    }
                }
            }
        }
    }

    public function reserve(int $limit, int $reservation): string
    {
        $period = now('UTC')->format('Y-m');
        DB::transaction(function () use ($period, $limit, $reservation): void {
            DB::table('meta_api_budgets')->insertOrIgnore(['period' => $period, 'committed_micros' => 0, 'requests' => 0, 'created_at' => now(), 'updated_at' => now()]);
            $row = DB::table('meta_api_budgets')->where('period', $period)->lockForUpdate()->first();
            if ($limit <= 0 || (int) $row->committed_micros + $reservation > $limit) {
                $needed = number_format($reservation / 1000000, 2);
                throw new RuntimeException("Meta API spending limit reached or not set. This request needs \${$needed} of available budget (META_MONTHLY_LIMIT_USD).");
            }
            DB::table('meta_api_budgets')->where('period', $period)->update([
                'committed_micros' => (int) $row->committed_micros + $reservation,
                'requests' => (int) $row->requests + 1,
                'updated_at' => now(),
            ]);
        }, 5);

        return $period;
    }

    public function settle(string $period, array $usage, int $reservation, string $model): void
    {
        $input = $usage['prompt_tokens'] ?? null;
        $output = $usage['completion_tokens'] ?? null;
        if (! is_int($input) || ! is_int($output) || $input < 0 || $output < 0 || $input > self::MAX_INPUT_TOKENS || $output > self::MAX_OUTPUT_TOKENS) {
            return;
        }
        $this->release($period, $reservation - min(self::costFor($model, $input, $output), $reservation));
    }

    public function settleImages(string $period, mixed $images, int $reservation, string $model): void
    {
        if (! is_array($images)) {
            return;
        }
        $this->release($period, $reservation - min(count($images) * (self::IMAGE_MODELS[$model] ?? 0), $reservation));
    }

    public function settleAudio(string $period, mixed $audioMs, int $reservation): void
    {
        if (! is_int($audioMs) || $audioMs < 0) {
            return;
        }
        $this->release($period, $reservation - min(self::costForAudio($audioMs), $reservation));
    }

    public function release(string $period, int $micros): void
    {
        if ($micros <= 0) {
            return;
        }
        DB::table('meta_api_budgets')->where('period', $period)->decrement('committed_micros', $micros);
    }

    /** @return array{period: string, committed_usd: float, limit_usd: float, requests: int} */
    public function status(): array
    {
        $period = now('UTC')->format('Y-m');
        $row = DB::table('meta_api_budgets')->where('period', $period)->first();

        return [
            'period' => $period,
            'committed_usd' => round((int) ($row->committed_micros ?? 0) / 1000000, 6),
            'limit_usd' => (float) config('tbwim.meta.monthly_limit_usd', 0),
            'requests' => (int) ($row->requests ?? 0),
        ];
    }
}
