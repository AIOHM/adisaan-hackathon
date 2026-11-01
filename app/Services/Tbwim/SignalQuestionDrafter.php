<?php

declare(strict_types=1);

namespace App\Services\Tbwim;

use App\Services\Meta\MetaClient;
use Illuminate\Support\Facades\Log;

/**
 * Reads the day's published news signals and drafts tomorrow's question
 * from one of them, with the avatar brief that announces it. The draft is
 * only a draft: a person approves before it enters the rotation.
 */
final class SignalQuestionDrafter
{
    public const PROMPT_VERSION = 'signal-question-v1';

    public const INSTRUCTIONS = <<<'PROMPT'
You are the newsroom reader for AdiSaan, the TBWIM movement: one honest question at a time, answered by people and by AI agents, kept apart and published as consented data. You read today's AI signals and choose the one that can honestly become tomorrow's public question.

## VOICE
Plain words, short sentences, no hype, no corporate jargon. The question belongs to the movement's frame and to nobody's marketing. Never invent facts the signal does not contain; the why may only use what the signal says.

## WHAT TO WRITE
One JSON object and nothing else:
{"signal_index": 0, "question": "...", "why": "...", "brief_script": "..."}

- signal_index: the number of the signal you chose from the list given.
- question: one sentence, ending in a question mark, always starting with the brand frame `What is the best way I imagine`, then a topic a person can answer from their own life. Example: `What is the best way I imagine to prove a recorded voice is really mine?` Under 180 characters. It must work without having read the article.
- why: 25 to 70 words on why this signal earns tomorrow's question: what changed, who it touches, what honest disagreement it opens.
- brief_script: 90 to 150 words, first person as AdiSaan, spoken aloud in about a minute. Open with the news in one or two plain sentences ("In today's news..."), then the turn ("...so tomorrow I ask:"), then the question word for word, then one warm closing sentence inviting answers from people and from agents. No stage directions, no brackets, no list formatting.

## JSON HYGIENE
Escape every double quote inside a string. No literal line breaks inside a string, no trailing commas, no text outside the object.
PROMPT;

    public function __construct(private readonly MetaClient $meta) {}

    /**
     * @param  list<array{title: string, excerpt: string, source: string, link: string, category_label?: string}>  $signals
     * @return array{signal_index: int, question: string, why: string, brief_script: string, model: string, prompt_version: string, prompt_tokens: ?int, completion_tokens: ?int}
     */
    public function draft(array $signals): array
    {
        if ($signals === []) {
            throw new \RuntimeException('No signals to draft from.');
        }
        $lines = [];
        foreach (array_values($signals) as $i => $signal) {
            $lines[] = $i.'. ['.($signal['source'] ?? 'wire').'] '.trim((string) $signal['title']).' — '.trim((string) ($signal['excerpt'] ?? ''));
        }
        $input = "TODAY'S SIGNALS:\n".implode("\n", $lines);

        $lastProblem = null;
        $lastResponse = '';
        for ($attempt = 1; $attempt <= 2; $attempt++) {
            $prompt = $attempt === 1 ? $input : $input."\n\nYour previous answer was rejected: {$lastProblem}. Fix exactly that and answer again with the JSON only.";
            $result = $this->meta->chat([
                ['role' => 'system', 'content' => self::INSTRUCTIONS],
                ['role' => 'user', 'content' => $prompt],
            ], (string) config('tbwim.signal_question.model'), 4096, 0.4);
            $lastResponse = $result['text'];
            $parsed = $this->parse($result['text'], count($signals));
            if (isset($parsed['error'])) {
                $lastProblem = $parsed['error'];
                Log::warning('tbwim.signal_question.rejected', ['attempt' => $attempt, 'problem' => $lastProblem]);

                continue;
            }

            return $parsed + ['model' => $result['model'], 'prompt_version' => self::PROMPT_VERSION,
                'prompt_tokens' => $result['prompt_tokens'], 'completion_tokens' => $result['completion_tokens']];
        }
        throw new \RuntimeException('The model gave no usable signal question after two attempts: '.$lastProblem.' Last response: '.mb_substr($lastResponse, 0, 300));
    }

    /** @return array{signal_index: int, question: string, why: string, brief_script: string}|array{error: string} */
    public function parse(string $text, int $signalCount): array
    {
        $data = MetaClient::extractJson($text);
        if ($data === null) {
            return ['error' => 'the response was not a JSON object'];
        }
        $index = $data['signal_index'] ?? null;
        if (! is_int($index) || $index < 0 || $index >= $signalCount) {
            return ['error' => 'signal_index must name one of the signals given'];
        }
        $question = trim(preg_replace('/\s+/u', ' ', (string) ($data['question'] ?? '')) ?? '');
        $why = trim(preg_replace('/\s+/u', ' ', (string) ($data['why'] ?? '')) ?? '');
        $brief = trim(preg_replace('/\s+/u', ' ', (string) ($data['brief_script'] ?? '')) ?? '');
        if ($question === '' || $why === '' || $brief === '') {
            return ['error' => 'question, why and brief_script must all be non-empty'];
        }
        if (! str_starts_with($question, 'What is the best way I imagine') || ! str_ends_with($question, '?') || substr_count($question, '?') !== 1) {
            return ['error' => 'the question must be one sentence in the brand frame, ending in a question mark'];
        }
        $whyWords = str_word_count($why);
        if ($whyWords < 25 || $whyWords > 70) {
            return ['error' => "the why must be 25 to 70 words, got {$whyWords}"];
        }
        $briefWords = str_word_count($brief);
        if ($briefWords < 90 || $briefWords > 150) {
            return ['error' => "the brief_script must be 90 to 150 words, got {$briefWords}"];
        }
        if (! str_contains($brief, $question)) {
            return ['error' => 'the brief_script must speak the question word for word'];
        }

        return ['signal_index' => $index, 'question' => $question, 'why' => $why, 'brief_script' => $brief];
    }
}
