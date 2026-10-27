<?php

declare(strict_types=1);

namespace App\Services\Meta;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * The one door to Meta Model API. Every call goes through the spending cap,
 * uses a Standard-mode model identifier, and returns the text plus the usage
 * Meta reported, so a page can show what an answer cost.
 */
final class MetaClient
{
    public function __construct(private readonly MetaApiBudget $budget) {}

    public function configured(): bool
    {
        return trim((string) config('tbwim.meta.api_key', '')) !== '';
    }

    /**
     * @param  list<array{role: string, content: string}>  $messages
     * @return array{text: string, model: string, prompt_tokens: ?int, completion_tokens: ?int, latency_ms: int}
     */
    public function chat(array $messages, string $model, int $maxTokens = 1024, float $temperature = 0.3): array
    {
        $apiKey = trim((string) config('tbwim.meta.api_key', ''));
        if ($apiKey === '') {
            throw new RuntimeException('META_API_KEY is not set.');
        }
        $started = microtime(true);
        $response = Http::withMiddleware($this->budget->middleware())
            ->withToken($apiKey)
            ->acceptJson()
            ->timeout(120)
            ->post(config('tbwim.meta.url').'/chat/completions', [
                'model' => $model,
                'messages' => $messages,
                'max_tokens' => max(1, min(MetaApiBudget::MAX_OUTPUT_TOKENS, $maxTokens)),
                'temperature' => $temperature,
            ]);
        if (! $response->ok()) {
            throw new RuntimeException('Meta Model API returned HTTP '.$response->status().': '.mb_substr($response->body(), 0, 200));
        }
        $json = $response->json();

        return [
            'text' => trim((string) data_get($json, 'choices.0.message.content', '')),
            'model' => (string) data_get($json, 'model', $model),
            'prompt_tokens' => is_int($t = data_get($json, 'usage.prompt_tokens')) ? $t : null,
            'completion_tokens' => is_int($t = data_get($json, 'usage.completion_tokens')) ? $t : null,
            'latency_ms' => (int) round((microtime(true) - $started) * 1000),
        ];
    }

    /** Models like to wrap JSON in prose or a fence. Take the object. */
    public static function extractJson(string $text): ?array
    {
        $text = trim($text);
        if (preg_match('/```(?:json)?\s*(\{[\s\S]*?\})\s*```/s', $text, $m) === 1) {
            $text = $m[1];
        } else {
            $start = strpos($text, '{');
            $end = strrpos($text, '}');
            if ($start === false || $end === false || $end <= $start) {
                return null;
            }
            $text = substr($text, $start, $end - $start + 1);
        }
        $data = json_decode($text, true);

        return is_array($data) ? $data : null;
    }
}
