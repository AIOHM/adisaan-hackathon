<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\EchoSubmission;
use App\Services\Tbwim\VoiceTranscriber;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** Transcribes one recorded answer; a failure never blocks the intake. */
final class TranscribeVoiceAnswer implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    public int $tries = 2;

    public function __construct(public readonly string $receipt) {}

    public function handle(VoiceTranscriber $transcriber): void
    {
        $echo = EchoSubmission::query()->where('public_id', $this->receipt)->first();
        if (! $echo) {
            return;
        }
        $transcriber->transcribe($echo);
    }
}
