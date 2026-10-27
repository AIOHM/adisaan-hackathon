<?php

declare(strict_types=1);

namespace App\Services\Tbwim;

use App\Models\EchoSubmission;
use App\Services\Meta\MetaClient;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

/**
 * Turns a recorded answer into text. The transcript lands in its own
 * column; the submitted body is never touched, and transcription never
 * releases anything — a person still approves every human answer.
 * Quarantined recordings are transcribed too: the transcript helps the
 * person decide, and the spending cap bounds abuse.
 */
final class VoiceTranscriber
{
    /** Terminal voice states: the pending command leaves these alone. */
    public const TERMINAL = ['done', 'unavailable', 'too_long', 'empty', 'missing', 'rejected'];

    /** @param ?callable(string): ?string $converter Test seam: input path in, WAV path out. */
    public function __construct(private readonly MetaClient $meta, private $converter = null) {}

    /** @return array{status: string, reason?: string} */
    public function transcribe(EchoSubmission $echo): array
    {
        $media = $echo->metadata['media'] ?? null;
        if (! is_array($media) || empty($media['path'])) {
            return ['status' => 'skipped', 'reason' => 'no recording'];
        }
        if (trim((string) $echo->transcript) !== '') {
            return ['status' => 'skipped', 'reason' => 'already transcribed'];
        }
        if (in_array($echo->metadata['voice']['status'] ?? null, self::TERMINAL, true)) {
            return ['status' => 'skipped', 'reason' => 'terminal voice state'];
        }
        $path = Storage::disk((string) ($media['disk'] ?? 'local'))->path((string) $media['path']);
        if (! is_file($path)) {
            return $this->mark($echo, 'missing', 'recording is not on disk');
        }
        if (! Process::run('command -v ffmpeg')->successful()) {
            return $this->mark($echo, 'unavailable', 'no audio converter on this host');
        }
        $seconds = $this->probeSeconds($path);
        if ($seconds !== null && $seconds > 600) {
            return $this->mark($echo, 'too_long', 'clip is over the 10 minute API limit');
        }
        $wav = $this->convert($path);
        if ($wav === null) {
            Log::warning('tbwim.voice.convert_failed', ['receipt' => $echo->public_id]);

            return ['status' => 'failed', 'reason' => 'conversion failed'];
        }
        try {
            $result = $this->meta->transcribe($wav);
        } catch (\Throwable $e) {
            Log::warning('tbwim.voice.failed', ['receipt' => $echo->public_id, 'error' => $e->getMessage()]);
            if (str_contains($e->getMessage(), 'HTTP 400')) {
                return $this->mark($echo, 'rejected', 'the API refused this clip');
            }

            return ['status' => 'failed', 'reason' => $e->getMessage()];
        } finally {
            @unlink($wav);
        }
        if ($result['transcript'] === '') {
            return $this->mark($echo, 'empty', 'the clip holds no speech', ['model' => $result['model'], 'audio_ms' => $result['audio_ms']]);
        }
        $echo->transcript = $result['transcript'];
        $metadata = $echo->metadata ?? [];
        $metadata['voice'] = ['status' => 'done', 'model' => $result['model'], 'audio_ms' => $result['audio_ms'], 'transcribed_at' => now()->toIso8601String()];
        $echo->metadata = $metadata;
        $echo->save();

        return ['status' => 'done'];
    }

    /** @return array{status: string, reason: string} */
    private function mark(EchoSubmission $echo, string $status, string $reason, array $extra = []): array
    {
        $metadata = $echo->metadata ?? [];
        $metadata['voice'] = ['status' => $status, 'reason' => $reason] + $extra;
        $echo->metadata = $metadata;
        $echo->save();

        return ['status' => $status, 'reason' => $reason];
    }

    private function probeSeconds(string $path): ?float
    {
        $probe = Process::run('command -v ffprobe');
        if (! $probe->successful()) {
            return null;
        }
        $out = Process::run(['ffprobe', '-v', 'error', '-show_entries', 'format=duration', '-of', 'csv=p=0', $path]);
        if (! $out->successful()) {
            return null;
        }
        $seconds = (float) trim($out->output());

        return $seconds > 0 ? $seconds : null;
    }

    /** Normalize to what the API takes: mono 16 kHz WAV. Null when it fails. */
    private function convert(string $path): ?string
    {
        if ($this->converter !== null) {
            return ($this->converter)($path);
        }
        $base = tempnam(sys_get_temp_dir(), 'tbwim-voice-');
        if ($base === false) {
            return null;
        }
        @unlink($base);
        $wav = $base.'.wav';
        $done = Process::timeout(240)->run(['ffmpeg', '-y', '-v', 'error', '-i', $path, '-ac', '1', '-ar', '16000', '-c:a', 'pcm_s16le', $wav]);
        if (! $done->successful() || ! is_file($wav)) {
            @unlink($wav);

            return null;
        }

        return $wav;
    }
}
