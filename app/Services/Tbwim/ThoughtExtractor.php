<?php

declare(strict_types=1);

namespace App\Services\Tbwim;

use App\Services\Meta\MetaClient;
use Illuminate\Support\Facades\Log;

/**
 * The founder's thought, distilled. Reads one transcript (or text) and
 * writes the one question it answers, the answer in the speaker's own
 * voice, and the bonus: why this answer is valid and belongs in the human
 * imagination database. The caller stores the draft; this never does.
 */
final class ThoughtExtractor
{
    public const PROMPT_VERSION = 'thought-v3-tbwim';

    public const INSTRUCTIONS = <<<'PROMPT'
You read one founder's recorded thought and distil it into the triple the human imagination database keeps: the question, the answer, the why.

## VOICE
Plain words, short sentences, no corporate jargon, no hype. The answer keeps the speaker's meaning and first person where they used it. Never invent facts, numbers, names or promises the thought does not contain. Write every answer so any human can read it and feel it is also about them: another person's biography is not the reader's, so never carry their children, their losses or their career into the answer as everyone's facts. Take what such a story proves and state it as a truth anyone can check in their own life.

## WHAT TO WRITE
One JSON object and nothing else:
{"question": "the one question this thought answers", "answer": "the distilled answer", "why_valid": "why this answer is valid and belongs in the database"}
The question is a single sentence ending in a question mark, and it always starts with the brand frame `What is the best way I imagine`, then the topic in the thought's own language. Example: `What is the best way I imagine to structure a small team?` Never write the question any other way. The answer is 30 to 150 words drawn only from what was said, and it is built around the one rule or stance the speaker actually lives by: find the strongest sentence in the thought that says what they do or believe, and lead with it in their own words, first person. Stories, memories and examples are evidence for that rule, not the answer itself — compress each to a clause, never retell them. The test: a stranger reading only the answer should feel it was written about their life too. The why_valid is 30 to 120 words: what makes the answer hold up for anyone — reasoning or evidence any reader can test in their own life, not only the writer's biography — and why it deserves its place beside other human answers.

## JSON HYGIENE
Escape every double quote inside a string. No literal line breaks inside a string, no trailing commas, no text outside the object.
PROMPT;

    public function __construct(private readonly MetaClient $meta) {}

    /** @return array{question: string, answer: string, why_valid: string, model: string, prompt_version: string, prompt_tokens: ?int, completion_tokens: ?int} */
    public function extract(string $text): array
    {
        $input = 'THOUGHT:'."\n".trim(preg_replace('/\s+/u', ' ', $text) ?? '');
        $lastProblem = null;
        $lastResponse = '';
        for ($attempt = 1; $attempt <= 2; $attempt++) {
            $prompt = $attempt === 1 ? $input : $input."\n\nYour previous answer was rejected: {$lastProblem}. Fix exactly that and answer again with the JSON only.";
            $result = $this->meta->chat([
                ['role' => 'system', 'content' => self::INSTRUCTIONS],
                ['role' => 'user', 'content' => $prompt],
                // Reasoning eats output tokens too (measured on review and
                // synthesis): 2048 comes back empty, 4096 leaves room.
            ], (string) config('tbwim.extraction.model'), 4096, 0.3);
            $lastResponse = $result['text'];
            $parsed = $this->parse($result['text']);
            if (isset($parsed['error'])) {
                $lastProblem = $parsed['error'];
                Log::warning('tbwim.thought.rejected', ['attempt' => $attempt, 'problem' => $lastProblem]);

                continue;
            }

            return $parsed + ['model' => $result['model'], 'prompt_version' => self::PROMPT_VERSION,
                'prompt_tokens' => $result['prompt_tokens'], 'completion_tokens' => $result['completion_tokens']];
        }
        throw new \RuntimeException('The model gave no usable thought after two attempts: '.$lastProblem.' Last response: '.mb_substr($lastResponse, 0, 300));
    }

    /** @return array{question: string, answer: string, why_valid: string}|array{error: string} */
    public function parse(string $text): array
    {
        $data = MetaClient::extractJson($text);
        if ($data === null) {
            return ['error' => 'the response was not a JSON object'];
        }
        $question = trim(preg_replace('/\s+/u', ' ', (string) ($data['question'] ?? '')) ?? '');
        $answer = trim(preg_replace('/\s+/u', ' ', (string) ($data['answer'] ?? '')) ?? '');
        $why = trim(preg_replace('/\s+/u', ' ', (string) ($data['why_valid'] ?? '')) ?? '');
        if ($question === '' || $answer === '' || $why === '') {
            return ['error' => 'question, answer and why_valid must all be non-empty'];
        }
        if (! str_ends_with($question, '?')) {
            return ['error' => 'the question must be one sentence ending in a question mark'];
        }
        if (preg_match('/^what is the best way i imagine\b/i', $question) !== 1) {
            return ['error' => 'the question must start with "What is the best way I imagine"'];
        }
        if (mb_strlen($question) > 280) {
            return ['error' => 'the question must be at most 280 characters'];
        }
        foreach (['answer' => [$answer, 30, 150], 'why_valid' => [$why, 30, 120]] as $field => [$value, $min, $max]) {
            $words = str_word_count($value);
            if ($words < $min || $words > $max) {
                return ['error' => "the {$field} has {$words} words, it must have {$min} to {$max}"];
            }
        }

        return ['question' => $question, 'answer' => $answer, 'why_valid' => $why];
    }
}
