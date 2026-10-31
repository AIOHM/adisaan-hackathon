<?php

declare(strict_types=1);

namespace App\Services\Tbwim;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * The dispatch's newsroom: fetches the configured RSS/Atom feeds and the
 * YouTube channel feed, normalizes items, and caches everything. A feed
 * that fails returns no items (marked not-ok for the status widget) —
 * the page never 500s because a source is down.
 *
 * @phpstan-type NewsItem array{title: string, link: string, source: string, published_at: ?Carbon, excerpt: string, image: ?string, category: string, category_label: string}
 */
final class NewsReader
{
    /** @return list<array{url: string, category: string, label: string}> */
    public function feeds(): array
    {
        $rows = [];
        foreach ((array) config('tbwim_news.feeds', []) as $feed) {
            if (! is_array($feed) || empty($feed['url']) || empty($feed['category'])) {
                continue;
            }
            $rows[] = [
                'url' => (string) $feed['url'],
                'category' => (string) $feed['category'],
                'label' => (string) ($feed['label'] ?? $feed['category']),
            ];
        }

        return $rows;
    }

    /** @return array{items: list<NewsItem>, ok: bool} */
    public function feed(string $url, string $category): array
    {
        $key = 'tbwim.news.feed.'.md5($url.'|'.$category);
        $cached = Cache::get($key);
        if (is_array($cached) && isset($cached['items'])) {
            return $cached;
        }

        $result = ['items' => [], 'ok' => false];
        try {
            $response = Http::timeout(12)->accept('application/rss+xml, application/atom+xml, application/xml, text/xml')->get($url);
            if ($response->successful()) {
                $result = ['items' => $this->parse($response->body(), $category), 'ok' => true];
            }
        } catch (\Throwable) {
            $result = ['items' => [], 'ok' => false];
        }

        // Failures cache briefly so one down feed cannot hammer the page.
        Cache::put($key, $result, now()->addMinutes($result['ok'] ? (int) config('tbwim_news.cache_minutes', 60) : 5));

        return $result;
    }

    /** @return list<NewsItem> newest first, optionally filtered */
    public function signals(?string $category = null, ?string $query = null): array
    {
        $items = [];
        foreach ($this->feeds() as $feed) {
            if ($category !== null && $category !== '' && $feed['category'] !== $category) {
                continue;
            }
            $items = array_merge($items, $this->feed($feed['url'], $feed['category'])['items']);
        }

        if ($query !== null && trim($query) !== '') {
            $needle = mb_strtolower(trim($query));
            $items = array_values(array_filter($items, static fn (array $item): bool => str_contains(mb_strtolower($item['title'].' '.$item['excerpt'].' '.$item['source']), $needle)));
        }

        usort($items, static fn (array $a, array $b): int => ($b['published_at']?->timestamp ?? 0) <=> ($a['published_at']?->timestamp ?? 0));

        return $items;
    }

    /** @return array{total: int, per: array<string, int>} */
    public function counts(): array
    {
        $per = [];
        foreach ($this->feeds() as $feed) {
            $n = count($this->feed($feed['url'], $feed['category'])['items']);
            $per[$feed['category']] = ($per[$feed['category']] ?? 0) + $n;
        }

        return ['total' => array_sum($per), 'per' => $per];
    }

    /** @return list<array{label: string, items: int, ok: bool}> */
    public function streams(): array
    {
        $rows = [];
        foreach ($this->feeds() as $feed) {
            $fetched = $this->feed($feed['url'], $feed['category']);
            $rows[] = ['label' => $feed['label'], 'items' => count($fetched['items']), 'ok' => $fetched['ok']];
        }

        return $rows;
    }

    /** @return list<array{id: string, title: string, link: string, thumb: string, published: ?Carbon}> */
    public function videos(int $limit = 4): array
    {
        $channel = (string) config('tbwim_news.youtube_channel_id', '');
        if ($channel === '') {
            return [];
        }

        $videos = Cache::remember(
            'tbwim.news.youtube.'.$channel,
            now()->addMinutes((int) config('tbwim_news.cache_minutes', 60)),
            function () use ($channel): array {
                try {
                    $response = Http::timeout(12)->get('https://www.youtube.com/feeds/videos.xml', ['channel_id' => $channel]);
                    if (! $response->successful()) {
                        return [];
                    }

                    return $this->parseVideos($response->body());
                } catch (\Throwable) {
                    return [];
                }
            }
        );

        if ($videos === []) {
            $videos = array_map(static fn (array $row): array => [
                'id' => (string) $row['id'],
                'title' => (string) $row['title'],
                'link' => 'https://www.youtube.com/watch?v='.$row['id'],
                'thumb' => 'https://i.ytimg.com/vi/'.$row['id'].'/hqdefault.jpg',
                'published' => null,
            ], array_filter((array) config('tbwim_news.youtube_fallback', []), static fn (mixed $row): bool => is_array($row) && ! empty($row['id'])));
        }

        return array_slice(array_values($videos), 0, max(1, $limit));
    }

    /** @return list<NewsItem> */
    public function parse(string $xml, string $category): array
    {
        $doc = $this->loadXml($xml);
        if ($doc === null) {
            return [];
        }

        $labels = (array) config('tbwim_news.categories', []);
        $label = (string) ($labels[$category]['label'] ?? $category);
        $max = max(1, (int) config('tbwim_news.max_items_per_feed', 60));

        $nodes = isset($doc->channel->item) ? $doc->channel->item : $doc->xpath('//*[local-name()="entry"]');
        $items = [];
        foreach ($nodes ?: [] as $node) {
            $title = trim(html_entity_decode((string) ($node->title ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if ($title === '') {
                continue;
            }
            $link = $this->nodeLink($node);
            $html = (string) ($node->description ?? $node->children()->{'content'} ?? $node->summary ?? $node->children()->{'content:encoded'} ?? '');
            $items[] = [
                'title' => $title,
                'link' => $link !== '' ? $link : '#',
                'source' => $this->nodeSource($node, $link),
                'published_at' => $this->nodeDate((string) ($node->pubDate ?? $node->published ?? $node->updated ?? '')),
                'excerpt' => Str::limit(trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? ''), 180),
                'image' => $this->nodeImage($node, $html),
                'category' => $category,
                'category_label' => $label,
            ];
            if (count($items) >= $max) {
                break;
            }
        }

        return $items;
    }

    private function nodeLink(\SimpleXMLElement $node): string
    {
        $direct = trim((string) ($node->link ?? ''));
        if ($direct !== '') {
            return $direct;
        }
        foreach ($node->link as $link) {
            $href = trim((string) ($link['href'] ?? ''));
            if ($href !== '') {
                return $href;
            }
        }
        $guid = trim((string) ($node->guid ?? $node->id ?? ''));

        return str_starts_with($guid, 'http') ? $guid : '';
    }

    private function nodeSource(\SimpleXMLElement $node, string $link): string
    {
        $named = trim((string) ($node->source['name'] ?? $node->source ?? ''));
        if ($named !== '') {
            return $named;
        }
        $host = (string) parse_url($link, PHP_URL_HOST);

        return $host !== '' ? (string) preg_replace('/^www\./', '', $host) : 'Dispatch wire';
    }

    private function nodeDate(string $raw): ?Carbon
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
        try {
            return Carbon::parse($raw);
        } catch (\Throwable) {
            return null;
        }
    }

    private function nodeImage(\SimpleXMLElement $node, string $html): ?string
    {
        foreach ($node->enclosure as $enclosure) {
            $type = (string) ($enclosure['type'] ?? '');
            $url = trim((string) ($enclosure['url'] ?? ''));
            if ($url !== '' && ($type === '' || str_starts_with($type, 'image/'))) {
                return $url;
            }
        }
        $media = $node->children('media', true);
        foreach (['content', 'thumbnail'] as $tag) {
            $url = trim((string) ($media->{$tag}['url'] ?? ''));
            if ($url !== '') {
                return $url;
            }
        }
        if (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $html, $match) === 1) {
            $src = trim(html_entity_decode($match[1]));
            if (str_starts_with($src, 'http')) {
                return $src;
            }
        }

        return null;
    }

    /** @return list<array{id: string, title: string, link: string, thumb: string, published: ?Carbon}> */
    private function parseVideos(string $xml): array
    {
        $doc = $this->loadXml($xml);
        if ($doc === null) {
            return [];
        }
        $videos = [];
        foreach ($doc->xpath('//*[local-name()="entry"]') ?: [] as $entry) {
            /** @var \SimpleXMLElement $entry */
            $yt = $entry->children('yt', true);
            $id = trim((string) ($yt->videoId ?? ''));
            $title = trim((string) ($entry->title ?? ''));
            if ($id === '' || $title === '') {
                continue;
            }
            $videos[] = [
                'id' => $id,
                'title' => $title,
                'link' => 'https://www.youtube.com/watch?v='.$id,
                'thumb' => 'https://i.ytimg.com/vi/'.$id.'/hqdefault.jpg',
                'published' => $this->nodeDate((string) ($entry->published ?? '')),
            ];
        }

        return $videos;
    }

    private function loadXml(string $xml): ?\SimpleXMLElement
    {
        if (trim($xml) === '') {
            return null;
        }
        libxml_use_internal_errors(true);
        $doc = simplexml_load_string($xml, \SimpleXMLElement::class, LIBXML_NOCDATA);
        libxml_clear_errors();

        return $doc === false ? null : $doc;
    }
}
