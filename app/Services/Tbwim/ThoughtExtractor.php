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
    public const PROMPT_VERSION = 'thought-v1-meta';

    public const INSTRUCTIONS = <<<'PROMPT'
You read one founder's recorded thought and distil it into the triple the human imagination database keeps: the question, the answer, the why.

## VOICE
Plain words, short sentences, no corporate jargon, no hype. The answer keeps the speaker's meaning and first person where they used it. Never invent facts, numbers, names or promises the thought does not contain.

## WHAT TO WRITE
One JSON object and nothing else:
{"question": "the one question this thought answers", "answer": "the distilled answer", "why_valid": "why this answer is valid and belongs in the database"}
The question is a single sentence ending in a question mark, in the thought's own language. The answer is 30 to 150 words drawn only from what was said. The why_valid is 30 to 120 words: what makes the answer hold up (lived experience, reasoning, evidence in the thought) and why it deserves its place beside other human answers.
PROMPT;

    public function __construct(private readonly MetaClient $meta) {}

    /** @return array{question: string, answer: string, why_valid: string, model: string, prompt_version: string, prompt_tokens: ?int, completion_tokens: ?int} */
    public function extract(string $text): array
    {
        $input = 'THOUGHT:'."\n".trim(preg_replace('/\s+/u', ' ', $text) ?? '');
        $lastProblem = null;
        for ($attempt = 1; $attempt <= 2; $attempt++) {
            $prompt = $attempt === 1 ? $input : $input."\n\nYour previous answer was rejected: {$lastProblem}. Fix exactly that and answer again with the JSON only.";
            $result = $this->meta->chat([
                ['role' => 'system', 'content' => self::INSTRUCTIONS],
                ['role' => 'user', 'content' => $prompt],
            ], (string) config('tbwim.extraction.model'), 2048, 0.3);
            $parsed = $this->parse($result['text']);
            if (isset($parsed['error'])) {
                $lastProblem = $parsed['error'];
                Log::warning('tbwim.thought.rejected', ['attempt' => $attempt, 'problem' => $lastProblem]);

                continue;
            }

            return $parsed + ['model' => $result['model'], 'prompt_version' => self::PROMPT_VERSION,
                'prompt_tokens' => $result['prompt_tokens'], 'completion_tokens' => $result['completion_tokens']];
        }
        throw new \RuntimeException('The model gave no usable thought after two attempts: '.$lastProblem);
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
