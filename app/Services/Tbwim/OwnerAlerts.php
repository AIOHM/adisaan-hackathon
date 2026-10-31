<?php

declare(strict_types=1);

namespace App\Services\Tbwim;

use App\Models\EchoSubmission;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * One-way Telegram pings to the owner: a human answer to review (with
 * signed release/reject links), or a new join-lead email. Sending only:
 * the shared platform bot's webhook belongs to the platform, so the
 * links below carry the decisions instead of callback buttons. False
 * when unconfigured or on transport failure; never throws.
 */
final class OwnerAlerts
{
    public function configured(): bool
    {
        return trim((string) config('tbwim.owner_alerts.bot_token', '')) !== ''
            && trim((string) config('tbwim.owner_alerts.chat_id', '')) !== '';
    }

    public function decideUrl(EchoSubmission $echo, string $decision): string
    {
        return URL::temporarySignedRoute(
            'tbwim.owner.decide',
            now()->addDays(max(1, (int) config('tbwim.owner_alerts.link_days', 7))),
            ['submission' => $echo->public_id, 'decision' => $decision]
        );
    }

    public function notifyAnswer(EchoSubmission $echo): bool
    {
        $meta = is_array($echo->metadata) ? $echo->metadata : [];
        $lines = [
            'New human answer to review',
            '',
            'Question: '.Str::limit(trim((string) ($echo->prompt_text ?: $echo->episode_ref)), 200),
            'From: '.trim((string) ($echo->signature ?: 'Anonymous')).($echo->consent_quote ? ' (public)' : ' (private)'),
        ];
        if (is_array($meta['media'] ?? null)) {
            $lines[] = 'Type: recording'.(trim((string) $echo->transcript) !== '' ? '' : ' (transcription pending)');
            if (trim((string) $echo->transcript) !== '') {
                $lines[] = 'Transcript: '.Str::limit(trim((string) $echo->transcript), 300);
            }
        }
        $lines[] = '';
        $lines[] = Str::limit(trim((string) $echo->body), 500);

        return $this->send(implode("\n", $lines), [
            [['text' => '✅ Release', 'url' => $this->decideUrl($echo, 'approve')],
                ['text' => '⛔ Reject', 'url' => $this->decideUrl($echo, 'reject')]],
        ]);
    }

    public function notifyLead(string $email, string $source): bool
    {
        return $this->send(implode("\n", [
            'New join email',
            '',
            'Email: '.$email,
            'Via: '.($source !== '' ? $source : 'unknown'),
        ]), []);
    }

    /** @param  list<list<array{text: string, url: string}>>  $keyboard */
    private function send(string $text, array $keyboard): bool
    {
        if (! $this->configured()) {
            return false;
        }
        $payload = [
            'chat_id' => trim((string) config('tbwim.owner_alerts.chat_id', '')),
            'text' => $text,
            'disable_web_page_preview' => true,
        ];
        if ($keyboard !== []) {
            $payload['reply_markup'] = ['inline_keyboard' => $keyboard];
        }
        try {
            $response = Http::timeout(20)->post(
                'https://api.telegram.org/bot'.trim((string) config('tbwim.owner_alerts.bot_token', '')).'/sendMessage',
                $payload
            );
        } catch (\Throwable $e) {
            Log::warning('tbwim.owner_alerts.failed', ['error' => $e->getMessage()]);

            return false;
        }
        if (! $response->ok()) {
            Log::warning('tbwim.owner_alerts.failed', ['status' => $response->status(), 'body' => mb_substr($response->body(), 0, 200)]);

            return false;
        }

        return true;
    }
}
