<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\ExtractThought;
use App\Jobs\TranscribeVoiceAnswer;
use App\Models\EchoSubmission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Founder capture intake: the platform's Telegram forward posts the
 * founder's thoughts here (text or one recording). Each becomes a private
 * founder echo, then transcription and Muse distillation into a draft.
 */
class CaptureController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $secret = (string) config('tbwim.capture.intake_secret', '');
        if ($secret === '' || ! hash_equals($secret, (string) $request->bearerToken())) {
            return response()->json(['ok' => false, 'error' => 'Unauthorized.'], 401);
        }

        $maxKb = max(1, intdiv((int) config('tbwim.capture.max_bytes', 40 * 1024 * 1024), 1024));
        $validated = $request->validate([
            'text' => ['nullable', 'string', 'max:6000'],
            'file' => ['nullable', 'file', 'mimes:mp3,m4a,wav,ogg,oga,webm,mp4,mov', "max:{$maxKb}"],
            'from_id' => ['required'],
            'message_id' => ['required'],
            'language' => ['nullable', 'string', 'max:12'],
        ]);

        $fromId = (string) $validated['from_id'];
        $allowed = array_map('strval', (array) config('tbwim.capture.founder_ids', []));
        if ($allowed !== [] && ! in_array($fromId, $allowed, true)) {
            return response()->json(['ok' => false, 'error' => 'Sender is not a founder.'], 403);
        }

        $text = trim((string) ($validated['text'] ?? ''));
        $file = $request->file('file');
        if ($text === '' && ! $file) {
            return response()->json(['ok' => false, 'error' => 'Nothing to capture: text or a recording is required.'], 422);
        }

        // Telegram retries deliveries; the same message must not distil twice.
        $messageId = (string) $validated['message_id'];
        $existing = EchoSubmission::query()
            ->where('metadata->telegram->from_id', $fromId)
            ->where('metadata->telegram->message_id', $messageId)
            ->first();
        if ($existing) {
            return response()->json(['ok' => true, 'duplicate' => true, 'receipt' => $existing->public_id]);
        }

        $mediaPath = null;
        if ($file) {
            $mediaPath = $file->store('founder-capture', 'local') ?: null;
            if ($mediaPath === null) {
                throw new \RuntimeException('Could not store the recording.');
            }
        }

        $echo = new EchoSubmission;
        $echo->episode_ref = 'founder-capture';
        $echo->prompt_key = 'founder-thought';
        $echo->body = $text !== '' ? $text : ($file && str_starts_with((string) $file->getMimeType(), 'audio/') ? '[Audio thought awaiting transcription]' : '[Video thought awaiting transcription]');
        $echo->signature = 'Adrian';
        $echo->language = Str::limit((string) ($validated['language'] ?? 'en'), 12, '');
        $echo->status = EchoSubmission::STATUS_RECEIVED;
        $echo->consent_quote = false;
        $echo->consent_followup = false;
        $echo->reviewed_at = now();
        $echo->review_reason = 'Founder capture: reviewed through thought approval, not the public queue.';
        $echo->metadata = [
            'founder_capture' => true,
            'respondent_type' => 'human',
            'source_channel' => 'telegram',
            'telegram' => ['from_id' => $fromId, 'message_id' => $messageId],
            'media' => $file ? ['disk' => 'local', 'path' => $mediaPath, 'mime' => $file->getMimeType(), 'size' => $file->getSize()] : null,
        ];
        try {
            $echo->save();
        } catch (\Throwable $e) {
            if ($mediaPath) {
                Storage::disk('local')->delete($mediaPath);
            }
            throw $e;
        }

        if ($mediaPath !== null) {
            TranscribeVoiceAnswer::dispatch($echo->public_id);
        } else {
            ExtractThought::dispatch($echo->public_id);
        }

        return response()->json(['ok' => true, 'receipt' => $echo->public_id], 201);
    }
}
