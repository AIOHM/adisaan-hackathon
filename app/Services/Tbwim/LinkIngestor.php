<?php

declare(strict_types=1);

namespace App\Services\Tbwim;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

/**
 * Turns a link the founder pastes into Telegram into distillable text.
 * A webpage gives its readable text; a YouTube video gives its captions;
 * anything that is (or points at) an audio/video file comes back as media
 * for the transcription pipeline. Failures say why in plain words — the
 * caller records the reason on the capture instead of guessing content.
 */
final class LinkIngestor
{
    private const UA = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36';

    private const MEDIA_EXT = ['mp3', 'm4a', 'wav', 'ogg', 'oga', 'mp4', 'webm', 'mov', 'm4v'];

    /** @param ?callable(string): ?string $vttFetcher Test seam: video id in, raw VTT out (null = yt-dlp unavailable or failed). */
    public function __construct(private $vttFetcher = null) {}

    /**
     * @return array{kind: 'text', title: string, text: string}|array{kind: 'media', media_url: string, media_ext: string}
     */
    public function ingest(string $url): array
    {
        $url = trim($url);
        if (! preg_match('#^https?://#i', $url)) {
            throw new \RuntimeException('That is not a link I can open.');
        }
        $this->assertPublicUrl($url);

        $youtubeId = $this->youtubeId($url);
        if ($youtubeId !== null) {
            return ['kind' => 'text', 'title' => 'YouTube video', 'text' => $this->youtubeTranscript($youtubeId)];
        }

        $ext = strtolower(pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
        if (in_array($ext, self::MEDIA_EXT, true)) {
            return ['kind' => 'media', 'media_url' => $url, 'media_ext' => $ext];
        }

        $html = $this->fetch($url);

        // A page that names its own video (video platforms, embeds) hands us
        // the media file; transcribe that instead of scraping thin text.
        if (preg_match('/<meta[^>]+(?:property|name)=["\'](?:og:video(?::url|:secure_url)?|twitter:player:stream)["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $m) === 1) {
            $mediaUrl = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $mediaExt = strtolower(pathinfo((string) parse_url($mediaUrl, PHP_URL_PATH), PATHINFO_EXTENSION));

            return ['kind' => 'media', 'media_url' => $mediaUrl, 'media_ext' => in_array($mediaExt, self::MEDIA_EXT, true) ? $mediaExt : 'mp4'];
        }

        $title = '';
        if (preg_match('/<meta[^>]+property=["\']og:title["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $m) === 1
            || preg_match('/<title[^>]*>([^<]+)<\/title>/i', $html, $m) === 1) {
            $title = trim(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }
        $text = $this->readableText($html);
        if (mb_strlen($text) < 200) {
            throw new \RuntimeException('That page gave almost no readable text (it may need a login or JavaScript).');
        }

        return ['kind' => 'text', 'title' => $title, 'text' => $text];
    }

    /** Download a media URL to a local path, enforcing the capture size cap. */
    public function download(string $url, string $destPath, int $maxBytes): void
    {
        $this->assertPublicUrl($url);
        $response = Http::withHeaders(['User-Agent' => self::UA])
            ->timeout(180)->sink($destPath)->get($url);
        if (! $response->ok()) {
            @unlink($destPath);
            throw new \RuntimeException('Downloading that media failed (HTTP '.$response->status().').');
        }
        if (is_file($destPath) && filesize($destPath) > $maxBytes) {
            @unlink($destPath);
            throw new \RuntimeException('That media file is over the capture size limit.');
        }
    }

    private function youtubeId(string $url): ?string
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $path = (string) parse_url($url, PHP_URL_PATH);
        if ($host === 'youtu.be') {
            $id = trim($path, '/');

            return $id !== '' ? $id : null;
        }
        if (in_array($host, ['youtube.com', 'www.youtube.com', 'm.youtube.com', 'music.youtube.com'], true)) {
            parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
            if (! empty($query['v'])) {
                return (string) $query['v'];
            }
            if (preg_match('#^/(?:shorts|live|embed)/([\w-]{6,})#', $path, $m) === 1) {
                return $m[1];
            }
        }

        return null;
    }

    private function youtubeTranscript(string $id): string
    {
        // YouTube now empties the unsigned caption endpoint for many
        // videos, so a host with yt-dlp gets the subtitles through it
        // first; the caption tracks stay as the fallback, and a video
        // neither route can read fails in plain words.
        $vtt = $this->vttFetcher !== null ? ($this->vttFetcher)($id) : $this->fetchVtt($id);
        if ($vtt !== null) {
            $text = $this->vttText($vtt);
            if (mb_strlen($text) >= 80) {
                return mb_substr($text, 0, 40000);
            }
        }

        return $this->youtubeCaptionTracks($id);
    }

    /** Raw subtitle file from yt-dlp, or null when the host cannot get one. */
    private function fetchVtt(string $id): ?string
    {
        if (! Process::run('command -v yt-dlp')->successful()) {
            return null;
        }
        $dir = sys_get_temp_dir().'/tbwim-yt-'.Str::ulid();
        if (! mkdir($dir, 0755, true)) {
            return null;
        }
        try {
            $cookies = (string) config('tbwim.capture.ytdlp_cookies', '');
            $args = ['yt-dlp', '--skip-download', '--write-subs', '--write-auto-subs',
                '--sub-langs', 'en.*', '--sub-format', 'vtt', '--no-playlist',
                '-o', $dir.'/video.%(ext)s', 'https://www.youtube.com/watch?v='.$id];
            if ($cookies !== '' && is_file($cookies)) {
                $args = array_merge(['yt-dlp', '--cookies', $cookies], array_slice($args, 1));
            }
            $done = Process::timeout(150)->run($args);
            if (! $done->successful()) {
                return null;
            }
            $files = glob($dir.'/*.vtt') ?: [];
            sort($files);
            $chosen = null;
            foreach ($files as $file) {
                if (str_ends_with($file, '.en.vtt')) {
                    $chosen = $file;
                    break;
                }
            }
            $chosen ??= $files[0] ?? null;

            return $chosen !== null ? (string) file_get_contents($chosen) : null;
        } finally {
            foreach (glob($dir.'/*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($dir);
        }
    }

    /** Clean subtitle text: no timing, no tags, no rolling repeats. */
    private function vttText(string $vtt): string
    {
        $lines = [];
        foreach (preg_split('/\R/', $vtt) ?: [] as $line) {
            $line = trim(strip_tags($line));
            if ($line === '' || $line === 'WEBVTT' || str_contains($line, '-->') || preg_match('/^(Kind|Language):/', $line) === 1) {
                continue;
            }
            $line = trim(preg_replace('/\s+/u', ' ', html_entity_decode($line, ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
            if ($line !== '' && end($lines) !== $line) {
                $lines[] = $line;
            }
        }

        return trim(implode(' ', $lines));
    }

    private function youtubeCaptionTracks(string $id): string
    {
        $html = $this->fetch('https://www.youtube.com/watch?v='.$id);
        $data = $this->playerResponse($html);
        $tracks = data_get($data, 'captions.playerCaptionsTracklistRenderer.captionTracks', []);
        if (! is_array($tracks) || $tracks === []) {
            throw new \RuntimeException('This video has no captions I can read.');
        }
        $chosen = null;
        foreach ($tracks as $track) {
            $lang = (string) ($track['languageCode'] ?? '');
            if (str_starts_with($lang, 'en') && ($track['kind'] ?? '') !== 'asr') {
                $chosen = $track;
                break;
            }
        }
        $chosen ??= $tracks[0];
        $base = (string) ($chosen['baseUrl'] ?? '');
        if ($base === '') {
            throw new \RuntimeException('This video has no captions I can read.');
        }
        $json = $this->fetch($base.'&fmt=json3');
        $events = json_decode($json, true)['events'] ?? [];
        $lines = [];
        foreach ((array) $events as $event) {
            $line = '';
            foreach ((array) ($event['segs'] ?? []) as $seg) {
                $line .= (string) ($seg['utf8'] ?? '');
            }
            $line = trim(preg_replace('/\s+/u', ' ', $line) ?? '');
            if ($line !== '' && end($lines) !== $line) {
                $lines[] = $line;
            }
        }
        $text = trim(implode("\n", $lines));
        if (mb_strlen($text) < 80) {
            throw new \RuntimeException('This video\'s captions are nearly empty.');
        }

        return mb_substr($text, 0, 40000);
    }

    /** @return array<string, mixed> */
    private function playerResponse(string $html): array
    {
        $marker = 'ytInitialPlayerResponse = ';
        $start = strpos($html, $marker);
        if ($start === false) {
            throw new \RuntimeException('That video page gave nothing readable.');
        }
        $start += strlen($marker);
        $depth = 0;
        $inString = false;
        $escaped = false;
        $length = strlen($html);
        for ($i = $start; $i < $length; $i++) {
            $ch = $html[$i];
            if ($inString) {
                if ($escaped) {
                    $escaped = false;
                } elseif ($ch === '\\') {
                    $escaped = true;
                } elseif ($ch === '"') {
                    $inString = false;
                }

                continue;
            }
            if ($ch === '"') {
                $inString = true;
            } elseif ($ch === '{') {
                $depth++;
            } elseif ($ch === '}') {
                $depth--;
                if ($depth === 0) {
                    $decoded = json_decode(substr($html, $start, $i - $start + 1), true);

                    return is_array($decoded) ? $decoded : [];
                }
            }
        }

        return [];
    }

    private function readableText(string $html): string
    {
        $html = preg_replace('#<(script|style|nav|footer|header|aside|form|noscript)\b[^>]*>.*?</\1>#is', ' ', $html) ?? $html;
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return mb_substr(trim($text), 0, 12000);
    }

    /**
     * Refuse links at this host or the private network. A hostname is only
     * refused when it actually resolves to a non-public address; an
     * unresolvable name is left for the fetch itself to report.
     */
    private function assertPublicUrl(string $url): void
    {
        $host = strtolower(trim((string) parse_url($url, PHP_URL_HOST), '[]'));
        if ($host === '' || $host === 'localhost' || str_ends_with($host, '.localhost') || str_ends_with($host, '.local')) {
            throw new \RuntimeException('That link points at a local address I will not open.');
        }
        $candidates = filter_var($host, FILTER_VALIDATE_IP) !== false ? [$host] : (gethostbynamel($host) ?: []);
        foreach ($candidates as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                throw new \RuntimeException('That link points at a private or reserved address I will not open.');
            }
        }
    }

    private function fetch(string $url): string
    {
        $this->assertPublicUrl($url);
        $response = Http::withHeaders(['User-Agent' => self::UA, 'Accept' => 'text/html,application/xhtml+xml'])
            ->timeout(20)->get($url);
        if (! $response->ok()) {
            throw new \RuntimeException('Opening that link failed (HTTP '.$response->status().').');
        }

        return $response->body();
    }
}
