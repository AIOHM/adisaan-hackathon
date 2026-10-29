<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\EchoSubmission;
use App\Models\FounderThought;
use App\Services\Tbwim\ThoughtExtractor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ExtractThought implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $receipt) {}

    public function handle(ThoughtExtractor $extractor): void
    {
        $echo = EchoSubmission::query()->where('public_id', $this->receipt)->first();
        if (! $echo || empty($echo->metadata['founder_capture'])) {
            return;
        }
        if (FounderThought::query()->where('echo_submission_id', $echo->id)->exists()) {
            return;
        }
        $text = trim((string) ($echo->transcript ?: $echo->body));
        if ($text === '' || str_starts_with($text, '[')) {
            return;
        }
        $result = $extractor->extract($text);
        FounderThought::create([
            'echo_submission_id' => $echo->id,
            'source_type' => ! empty($echo->metadata['media']) ? 'recording' : 'text',
            'question' => $result['question'],
            'answer' => $result['answer'],
            'why_valid' => $result['why_valid'],
            'status' => FounderThought::STATUS_DRAFT,
            'model' => $result['model'],
            'prompt_version' => $result['prompt_version'],
            'prompt_tokens' => $result['prompt_tokens'],
            'completion_tokens' => $result['completion_tokens'],
        ]);
    }
}
