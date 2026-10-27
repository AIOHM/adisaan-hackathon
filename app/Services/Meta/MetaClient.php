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

    /**
     * Transcribe one WAV clip. Multipart, so it reserves and settles
     * explicitly instead of through the JSON middleware; same ledger.
     *
     * @return array{transcript: string, audio_ms: ?int, model: string, latency_ms: int}
     */
    public function transcribe(string $wavPath, ?string $model = null): array
    {
        $model ??= (string) config('tbwim.voice.model', 'muse-voice-transcribe-1.0');
        if (! array_key_exists($model, MetaApiBudget::AUDIO_MODELS)) {
            throw new RuntimeException('Meta voice transcription supports muse-voice-transcribe-1.0 only.');
        }
        if (now('UTC')->greaterThan(config('tbwim.meta.pricing_review_before').' 00:00:00')) {
            throw new RuntimeException('Meta API pricing needs review before spending can resume (tbwim.meta.pricing_review_before).');
        }
        $apiKey = trim((string) config('tbwim.meta.api_key', ''));
        if ($apiKey === '') {
            throw new RuntimeException('META_API_KEY is not set.');
        }
        if (! is_file($wavPath) || ! is_readable($wavPath)) {
            throw new RuntimeException('Voice clip is not readable.');
        }
        $limit = (int) round((float) config('tbwim.meta.monthly_limit_usd', 0) * 1000000);
        $reservation = MetaApiBudget::reservationForAudio($model);
        $period = $this->budget->reserve($limit, $reservation);
        $started = microtime(true);
        $response = Http::withToken($apiKey)
            ->acceptJson()
            ->timeout(300)
            ->attach('request', (string) json_encode(['model' => $model, 'audioEncoding' => 'WAV', 'mode' => 'PUSH_TO_TALK']), 'request.json', ['Content-Type' => 'application/json'])
            ->attach('audio', (string) file_get_contents($wavPath), 'clip.wav', ['Content-Type' => 'audio/wav'])
            ->post(config('tbwim.meta.url').'/asr/transcribe');
        if ($response->status() === 401) {
            $this->budget->release($period, $reservation);

            throw new RuntimeException('Meta Model API rejected the key (HTTP 401).');
        }
        if (! $response->ok()) {
            throw new RuntimeException('Meta Model API returned HTTP '.$response->status().': '.mb_substr($response->body(), 0, 200));
        }
        $json = $response->json();
        $this->budget->settleAudio($period, $json['audioDurationMs'] ?? null, $reservation);

        return [
            'transcript' => trim((string) ($json['transcript'] ?? '')),
            'audio_ms' => is_int($json['audioDurationMs'] ?? null) ? $json['audioDurationMs'] : null,
            'model' => $model,
            'latency_ms' => (int) round((microtime(true) - $started) * 1000),
        ];
    }

    /**
     * Generate one poster image. JSON through the budget middleware.
     *
     * @return array{b64: string, model: string, latency_ms: int}
     */
    public function generateImage(string $prompt, string $size = '1536x1024'): array
    {
        $apiKey = trim((string) config('tbwim.meta.api_key', ''));
        if ($apiKey === '') {
            throw new RuntimeException('META_API_KEY is not set.');
        }
        $prompt = trim($prompt);
        if ($prompt === '') {
            throw new RuntimeException('Image prompt is empty.');
        }
        $started = microtime(true);
        $response = Http::withMiddleware($this->budget->middleware())
            ->withToken($apiKey)
            ->acceptJson()
            ->timeout(300)
            ->post(config('tbwim.meta.url').'/images/generations', [
                'model' => 'muse-image-1.0',
                'prompt' => $prompt,
                'n' => 1,
                'response_format' => 'b64_json',
                'size' => $size,
            ]);
        if (! $response->ok()) {
            throw new RuntimeException('Meta Model API returned HTTP '.$response->status().': '.mb_substr($response->body(), 0, 200));
        }
        $json = $response->json();
        $b64 = (string) data_get($json, 'data.0.b64_json', '');
        if ($b64 === '') {
            throw new RuntimeException('Meta Model API returned no image.');
        }

        return [
            'b64' => $b64,
            'model' => (string) data_get($json, 'model', 'muse-image-1.0'),
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
