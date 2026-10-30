<?php

declare(strict_types=1);

namespace App\Services\Tbwim;

use App\Services\Meta\MetaClient;
use Illuminate\Support\Facades\Log;

/**
 * The breathing answer. Reads every voice on a question and writes "the best
 * way they imagine" as one short answer in AdiSaan's voice, plus one theme
 * per voice. With Muse Spark's 1M-token context every voice goes in one
 * call; nothing is sampled. The caller stores the version; this never does.
 */
final class QuestionSynthesizer
{
    public const PROMPT_VERSION = 'breath-v4-public-mixed';

    public const INSTRUCTIONS = <<<'PROMPT'
You are Muse writing as AdiSaan, Adrian Aparaschivei's avatar. AdiSaan asks one honest question at a time; people and AI agents answer. Your job is to say, in AdiSaan's words, what those answers suggest: the living answer, rewritten each time new voices arrive.

## VOICE
Direct, calm, slightly spiritual, zero corporate jargon. Short sentences. One idea at a time. Distinguish human perspectives from AI responses; do not imply AI responses are human experiences. Plain words. End on a full thought.

## WHAT TO WRITE
One answer of MIN to MAX words. Count them. It describes "the best way they imagine", as the voices actually say it. Where voices disagree, say so in one sentence; do not pick a winner. Describe common themes and differences, clearly distinguishing human and AI responses; do not invent agreement. Paraphrase; never quote, name, or mention a platform, handle, city or age. No number beyond "most", "some", "a few", "one person". No price, offer, link, hashtag or emoji. If an earlier version is given, keep what still holds and change only what the new voices change.

## THEMES
Give every voice exactly one theme: two to four plain lower-case words, the same spelling for the same idea. Aim for 3 to 7 themes.

## OUTPUT
One JSON object and nothing else:
{"answer": "the answer", "themes": [{"theme": "control and logging", "voices": [1, 4, 9]}]}
Every voice number appears in exactly one theme.
PROMPT;

    public function __construct(private readonly MetaClient $meta) {}

    /**
     * @param  list<array{circle: string, text: string}>  $voices
     * @return array{body: string, words: int, themes: list<array{theme: string, voices: int}>, voice_themes: array<int, string>, model: string, prompt_version: string, prompt_tokens: ?int, completion_tokens: ?int}
     */
    public function synthesize(string $question, array $voices, ?string $previousBody = null, int $version = 1): array
    {
        if ($voices === []) {
            throw new \RuntimeException('No voices to synthesize.');
        }
        $min = (int) config('tbwim.synthesis.min_words', 60);
        $max = (int) config('tbwim.synthesis.max_words', 120);
        $instructions = str_replace(['MIN', 'MAX'], [(string) $min, (string) $max], self::INSTRUCTIONS);
        $prompt = $this->buildPrompt($question, $voices, $previousBody, $version);
        $lastProblem = null;
        for ($attempt = 1; $attempt <= 2; $attempt++) {
            $input = $attempt === 1 ? $prompt : $prompt."\n\nYour previous answer was rejected: {$lastProblem}. Fix exactly that and answer again with the JSON only.";
            $result = $this->meta->chat([
                ['role' => 'system', 'content' => $instructions],
                ['role' => 'user', 'content' => $input],
                // Same reasoning headroom as the reviewer: verdicts starved at
                // 1200, and synthesis reads every voice at once (2026-10-27).
            ], (string) config('tbwim.synthesis.model'), 4096, 0.5);
            $parsed = $this->parse($result['text'], count($voices), $min, $max);
            if (isset($parsed['error'])) {
                $lastProblem = $parsed['error'];
                Log::warning('tbwim.synthesis.rejected', ['attempt' => $attempt, 'problem' => $lastProblem]);

                continue;
            }

            return $parsed + ['model' => $result['model'], 'prompt_version' => self::PROMPT_VERSION,
                'prompt_tokens' => $result['prompt_tokens'], 'completion_tokens' => $result['completion_tokens']];
        }
        throw new \RuntimeException('The model gave no usable answer after two attempts: '.$lastProblem);
    }

    /** @param list<array{circle: string, text: string}> $voices */
    public function buildPrompt(string $question, array $voices, ?string $previousBody, int $version): string
    {
        $lines = ['QUESTION: '.trim($question), 'VERSION TO WRITE: '.$version, ''];
        if ($previousBody !== null && trim($previousBody) !== '') {
            $lines[] = 'EARLIER VERSION (keep what still holds, change only what the new voices change):';
            $lines[] = trim($previousBody);
            $lines[] = '';
        }
        $lines[] = 'VOICES ('.count($voices).'; "human" = a person; "agent" = an AI agent):';
        foreach (array_values($voices) as $i => $voice) {
            $text = mb_substr(preg_replace('/\s+/u', ' ', trim((string) ($voice['text'] ?? ''))) ?? '', 0, 1200);
            $lines[] = '['.($i + 1).'] ('.($voice['circle'] ?? 'site').') '.$text;
        }
        $lines[] = '';
        $lines[] = 'Write the answer and the themes as the JSON object described in your instructions.';

        return implode("\n", $lines);
    }

    /** @return array{body: string, words: int, themes: list<array{theme: string, voices: int}>, voice_themes: array<int, string>}|array{error: string} */
    public function parse(string $text, int $voiceCount, int $min, int $max): array
    {
        $data = MetaClient::extractJson($text);
        if ($data === null || ! is_string($data['answer'] ?? null)) {
            return ['error' => 'the response was not the JSON object with an "answer" string'];
        }
        $body = trim(preg_replace('/\s+/u', ' ', $data['answer']) ?? '');
        $words = self::countWords($body);
        if ($words < $min || $words > $max) {
            return ['error' => "the answer has {$words} words, it must have {$min} to {$max}"];
        }
        $voiceThemes = [];
        $themes = [];
        foreach (is_array($data['themes'] ?? null) ? $data['themes'] : [] as $row) {
            if (! is_array($row)) {
                continue;
            }
            $theme = mb_strtolower(trim(preg_replace('/\s+/u', ' ', (string) ($row['theme'] ?? '')) ?? ''));
            if ($theme === '' || mb_strlen($theme) > 80) {
                continue;
            }
            $count = 0;
            foreach (is_array($row['voices'] ?? null) ? $row['voices'] : [] as $n) {
                $n = (int) $n;
                if ($n < 1 || $n > $voiceCount || isset($voiceThemes[$n])) {
                    continue;
                }
                $voiceThemes[$n] = $theme;
                $count++;
            }
            if ($count > 0) {
                $themes[$theme] = ($themes[$theme] ?? 0) + $count;
            }
        }
        if ($voiceThemes === []) {
            return ['error' => 'no voice received a theme'];
        }
        arsort($themes);
        $rows = [];
        foreach ($themes as $theme => $count) {
            $rows[] = ['theme' => (string) $theme, 'voices' => (int) $count];
        }
        ksort($voiceThemes);

        return ['body' => $body, 'words' => $words, 'themes' => $rows, 'voice_themes' => $voiceThemes];
    }

    public static function countWords(string $text): int
    {
        $parts = preg_split('/\s+/u', trim($text)) ?: [];

        return count(array_filter($parts, static fn (string $w): bool => preg_match('/\p{L}|\p{N}/u', $w) === 1));
    }
}
