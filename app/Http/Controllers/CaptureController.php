<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\ExtractThought;
use App\Jobs\IngestCaptureLink;
use App\Jobs\TranscribeVoiceAnswer;
use App\Models\EchoSubmission;
use App\Services\Tbwim\DocumentTextExtractor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Founder capture intake: the platform's Telegram forward posts the
 * founder's thoughts here — typed words, one recording, a document with
 * words, or a link to a page, video or audio. Each becomes a private
 * founder echo, then transcription/reading and Muse distillation into
 * the question–answer–why draft a person approves.
 */
class CaptureController extends Controller
{
    private const MEDIA_EXT = ['mp3', 'm4a', 'wav', 'ogg', 'oga', 'webm', 'mp4', 'mov'];

    public function store(Request $request, DocumentTextExtractor $documents): JsonResponse
    {
        $secret = (string) config('tbwim.capture.intake_secret', '');
        if ($secret === '' || ! hash_equals($secret, (string) $request->bearerToken())) {
            return response()->json(['ok' => false, 'error' => 'Unauthorized.'], 401);
        }

        $maxKb = max(1, intdiv((int) config('tbwim.capture.max_bytes', 40 * 1024 * 1024), 1024));
        $validated = $request->validate([
            'text' => ['nullable', 'string', 'max:6000'],
            'url' => ['nullable', 'url', 'max:2048'],
            'file' => ['nullable', 'file', "max:{$maxKb}"],
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
        // A message that is only a link is a link capture, whatever field
        // the forward put it in.
        $url = trim((string) ($validated['url'] ?? ''));
        if ($url === '' && $text !== '' && preg_match('#^https?://\S+$#i', $text) === 1) {
            $url = $text;
            $text = '';
        }
        if ($text === '' && $url === '' && ! $file) {
            return response()->json(['ok' => false, 'error' => 'Nothing to capture: text, a link, a document or a recording is required.'], 422);
        }

        $fileExt = $file ? strtolower($file->getClientOriginalExtension()) : '';
        $isDocument = $file && in_array($fileExt, DocumentTextExtractor::EXTENSIONS, true);
        if ($file && ! $isDocument && ! in_array($fileExt, self::MEDIA_EXT, true)) {
            return response()->json(['ok' => false, 'error' => 'That file type cannot be captured. Send audio, video, or a txt, md, csv, pdf or docx document.'], 422);
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
        $documentText = null;
        $documentMeta = null;
        if ($isDocument) {
            try {
                $documentText = $documents->extract((string) $file->getRealPath(), $fileExt);
            } catch (\Throwable $e) {
                return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
            }
            $documentMeta = ['name' => $file->getClientOriginalName(), 'ext' => $fileExt, 'size' => $file->getSize()];
        } elseif ($file) {
            $mediaPath = $file->store('founder-capture', 'local') ?: null;
            if ($mediaPath === null) {
                throw new \RuntimeException('Could not store the recording.');
            }
        }

        $echo = new EchoSubmission;
        $echo->episode_ref = 'founder-capture';
        $echo->prompt_key = 'founder-thought';
        $echo->body = $documentText ?? ($url !== '' && $text === ''
            ? '[Link awaiting reading]'
            : ($text !== '' ? $text : ($file && str_starts_with((string) $file->getMimeType(), 'audio/') ? '[Audio thought awaiting transcription]' : '[Video thought awaiting transcription]')));
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
            'media' => $mediaPath !== null ? ['disk' => 'local', 'path' => $mediaPath, 'mime' => $file->getMimeType(), 'size' => $file->getSize()] : null,
            'document' => $documentMeta,
            'link' => $url !== '' && ! $file ? ['url' => $url, 'status' => 'pending'] : null,
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
        } elseif ($url !== '' && $documentText === null) {
            IngestCaptureLink::dispatch($echo->public_id);
        } else {
            ExtractThought::dispatch($echo->public_id);
        }

        // The platform forwarder accepts only an exact 200 (its ok()
        // check); a 201 made it report failure for captures that had
        // in fact landed, and the founder sent them again.
        return response()->json(['ok' => true, 'receipt' => $echo->public_id], 200);
    }
}
