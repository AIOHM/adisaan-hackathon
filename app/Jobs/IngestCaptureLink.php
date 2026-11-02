<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\EchoSubmission;
use App\Services\Tbwim\LinkIngestor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Reads the link on one founder capture. A page or video transcript becomes
 * the echo's words and goes on to distillation; a media link is downloaded
 * into the transcription pipeline. A link that cannot be read is recorded
 * as failed with the reason — never distilled from a guess.
 */
final class IngestCaptureLink implements ShouldQueue
{
    use Queueable;

    public int $timeout = 300;

    public int $tries = 1;

    public function __construct(public readonly string $receipt) {}

    public function handle(LinkIngestor $ingestor): void
    {
        $echo = EchoSubmission::query()->where('public_id', $this->receipt)->first();
        $url = (string) ($echo?->metadata['link']['url'] ?? '');
        if (! $echo || $url === '') {
            return;
        }

        try {
            $result = $ingestor->ingest($url);
        } catch (\Throwable $e) {
            $this->record($echo, ['status' => 'failed', 'reason' => $e->getMessage()]);
            Log::warning('tbwim.capture.link_failed', ['receipt' => $echo->public_id, 'reason' => $e->getMessage()]);

            return;
        }

        if ($result['kind'] === 'media') {
            $this->storeMedia($echo, $ingestor, $result['media_url'], $result['media_ext']);

            return;
        }

        $metadata = $echo->metadata ?? [];
        $metadata['link'] = ['url' => $url, 'status' => 'read', 'title' => $result['title']];
        $echo->metadata = $metadata;
        $echo->body = $result['text'];
        $echo->save();
        ExtractThought::dispatch($echo->public_id);
    }

    private function storeMedia(EchoSubmission $echo, LinkIngestor $ingestor, string $mediaUrl, string $ext): void
    {
        $relative = 'founder-capture/'.Str::ulid().'.'.$ext;
        $absolute = Storage::disk('local')->path($relative);
        if (! is_dir(dirname($absolute))) {
            mkdir(dirname($absolute), 0755, true);
        }
        try {
            $ingestor->download($mediaUrl, $absolute, (int) config('tbwim.capture.max_bytes', 40 * 1024 * 1024));
        } catch (\Throwable $e) {
            $this->record($echo, ['status' => 'failed', 'reason' => $e->getMessage()]);
            Log::warning('tbwim.capture.link_media_failed', ['receipt' => $echo->public_id, 'reason' => $e->getMessage()]);

            return;
        }
        $isAudio = in_array($ext, ['mp3', 'm4a', 'wav', 'ogg', 'oga'], true);
        $metadata = $echo->metadata ?? [];
        $metadata['link'] = ['url' => (string) ($metadata['link']['url'] ?? ''), 'status' => 'downloaded', 'media_url' => $mediaUrl];
        $metadata['media'] = ['disk' => 'local', 'path' => $relative, 'mime' => $isAudio ? 'audio/'.$ext : 'video/'.$ext, 'size' => filesize($absolute) ?: null];
        $echo->metadata = $metadata;
        $echo->body = $isAudio ? '[Audio thought awaiting transcription]' : '[Video thought awaiting transcription]';
        $echo->save();
        TranscribeVoiceAnswer::dispatch($echo->public_id);
    }

    /** @param array<string, string> $link */
    private function record(EchoSubmission $echo, array $link): void
    {
        $metadata = $echo->metadata ?? [];
        $metadata['link'] = ['url' => (string) ($metadata['link']['url'] ?? '')] + $link;
        $echo->metadata = $metadata;
        $echo->save();
    }
}
