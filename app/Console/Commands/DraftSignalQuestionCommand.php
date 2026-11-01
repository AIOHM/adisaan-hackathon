<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\SignalQuestion;
use App\Services\Tbwim\NewsReader;
use App\Services\Tbwim\SignalQuestionDrafter;
use Illuminate\Console\Command;

final class DraftSignalQuestionCommand extends Command
{
    protected $signature = 'tbwim:signal-question {--signals=12 : How many top signals to offer the model}';

    protected $description = "Draft tomorrow's question from today's news signals (Muse; a person still approves)";

    public function handle(NewsReader $reader, SignalQuestionDrafter $drafter): int
    {
        $signals = array_slice($reader->signals(), 0, max(1, (int) $this->option('signals')));
        if ($signals === []) {
            $this->error('No published signals right now; nothing to draft from.');

            return self::FAILURE;
        }

        try {
            $draft = $drafter->draft($signals);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $signal = $signals[$draft['signal_index']];
        $row = SignalQuestion::create([
            'signal_title' => (string) $signal['title'],
            'signal_url' => (string) $signal['link'],
            'signal_source' => (string) ($signal['source'] ?? ''),
            'signal_category' => (string) ($signal['category'] ?? ''),
            'question' => $draft['question'],
            'why' => $draft['why'],
            'brief_script' => $draft['brief_script'],
            'status' => SignalQuestion::STATUS_DRAFT,
            'model' => $draft['model'],
            'prompt_version' => $draft['prompt_version'],
            'prompt_tokens' => $draft['prompt_tokens'],
            'completion_tokens' => $draft['completion_tokens'],
        ]);

        $this->info("Draft #{$row->id} from [{$row->signal_source}] {$row->signal_title}");
        $this->line('Question: '.$row->question);
        $this->line('Approve: php artisan tbwim:signal-approve '.$row->id.' (or --reject)');

        return self::SUCCESS;
    }
}
