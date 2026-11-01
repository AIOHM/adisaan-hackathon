<?php

declare(strict_types=1);

namespace App\Services\Tbwim;

use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
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

        return $this->resolveImages($items);
    }

    /**
     * Publish only stories that carry the article's own picture (owner's
     * rule, 2026-11-01). A story whose feed gives no image gets one attempt
     * from the article page itself — its og:image or twitter:image, cached
     * per article (a week when found, a day when not). Whatever still has
     * no picture is not published at all; there is no homepage, front-page
     * or stock substitute. Publisher feeds that embed the story image in
     * the item already satisfy the rule in nodeImage().
     *
     * @param  list<NewsItem>  $items
     * @return list<NewsItem>
     */
    private function resolveImages(array $items): array
    {
        if (! (bool) config('tbwim_news.og_images', true)) {
            return $items;
        }

        $missing = [];
        foreach ($items as $i => $item) {
            if (! empty($item['image']) || ! str_starts_with((string) $item['link'], 'http')) {
                continue;
            }
            $cached = Cache::get('tbwim.news.og.'.md5((string) $item['link']));
            if (is_array($cached)) {
                $items[$i]['image'] = $cached['image'];
            } elseif (count($missing) < max(1, (int) config('tbwim_news.max_items_per_feed', 60))) {
                $missing[$i] = (string) $item['link'];
            }
        }

        if ($missing !== []) {
            $google = [];
            $direct = [];
            foreach ($missing as $i => $url) {
                if (parse_url($url, PHP_URL_HOST) === 'news.google.com') {
                    $google[$i] = $url;
                } else {
                    $direct[$i] = $url;
                }
            }
            $pages = $this->fetchDirect($direct) + $this->fetchViaCurl($google);
            foreach ($missing as $i => $url) {
                if (! isset($pages[$i])) {
                    continue; // Fetch failed; the next parse retries instead of caching a miss.
                }
                $image = $this->ogImage($pages[$i][0], $pages[$i][1]);
                Cache::put('tbwim.news.og.'.md5($url), ['image' => $image], $image !== null ? now()->addDays(7) : now()->addHour());
                $items[$i]['image'] = $image;
            }
        }

        return array_values(array_filter($items, static fn (array $item): bool => ! empty($item['image'])));
    }

    /**
     * Article pages through the Http client (also what tests fake).
     *
     * @param  array<int, string>  $urls
     * @return array<int, array{string, string}> item index => [body, effective URL]
     */
    private function fetchDirect(array $urls): array
    {
        if ($urls === []) {
            return [];
        }
        try {
            $indexes = array_keys($urls);
            $responses = Http::pool(fn (Pool $pool) => array_map(
                fn (string $url) => $pool->timeout(5)->get($url),
                array_values($urls),
            ));
        } catch (\Throwable) {
            return [];
        }
        $pages = [];
        foreach ($indexes as $position => $i) {
            $response = $responses[$position] ?? null;
            if ($response instanceof Response && $response->ok()) {
                $pages[$i] = [$response->body(), (string) ($response->effectiveUri() ?? $urls[$i])];
            }
        }

        return $pages;
    }

    /**
     * Google News links through the curl extension. Measured 2026-11-01:
     * Google consent-walls the Http client's request fingerprint (200 on
     * consent.google.com, no og:image) while the same URL through ext-curl
     * with a browser user-agent returns the article page and its picture.
     * Do not "simplify" this back to the Http client without re-measuring.
     *
     * @param  array<int, string>  $urls
     * @return array<int, array{string, string}> item index => [body, effective URL]
     */
    private function fetchViaCurl(array $urls): array
    {
        if ($urls === [] || ! function_exists('curl_multi_init')) {
            return [];
        }
        // Google throttles bursts: past ~15 simultaneous fetches the og
        // pages stop coming back. Small sequential chunks stay under it.
        $pages = [];
        foreach (array_chunk($urls, 10, true) as $chunk) {
            $pages += $this->fetchViaCurlChunk($chunk);
        }

        return $pages;
    }

    /** @param  array<int, string>  $urls
     *  @return array<int, array{string, string}> */
    private function fetchViaCurlChunk(array $urls): array
    {
        $multi = curl_multi_init();
        $handles = [];
        foreach ($urls as $i => $url) {
            $handle = curl_init($url);
            curl_setopt_array($handle, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 5,
                CURLOPT_TIMEOUT => 6,
                CURLOPT_CONNECTTIMEOUT => 4,
                CURLOPT_USERAGENT => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36',
            ]);
            curl_multi_add_handle($multi, $handle);
            $handles[$i] = $handle;
        }
        do {
            $status = curl_multi_exec($multi, $active);
            if ($active) {
                curl_multi_select($multi, 1.0);
            }
        } while ($active && $status === CURLM_OK);

        $pages = [];
        foreach ($handles as $i => $handle) {
            $code = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
            $body = curl_multi_getcontent($handle);
            if ($code >= 200 && $code < 300 && is_string($body)) {
                $pages[$i] = [$body, (string) curl_getinfo($handle, CURLINFO_EFFECTIVE_URL)];
            }
            curl_multi_remove_handle($multi, $handle);
            curl_close($handle);
        }
        curl_multi_close($multi);

        return $pages;
    }

    /** The publisher's picture for a page: og:image, then twitter:image, made absolute. */
    private function ogImage(string $html, string $pageUrl): ?string
    {
        foreach (['og:image', 'twitter:image'] as $property) {
            $patterns = [
                '/<meta[^>]+(?:property|name)=["\']'.$property.'["\'][^>]+content=["\']([^"\']+)["\']/i',
                '/<meta[^>]+content=["\']([^"\']+)["\'][^>]+(?:property|name)=["\']'.$property.'["\']/i',
            ];
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $html, $match) === 1) {
                    $src = trim(html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                    if ($src !== '') {
                        return $this->absoluteUrl($src, $pageUrl);
                    }
                }
            }
        }

        return null;
    }

    private function absoluteUrl(string $src, string $pageUrl): ?string
    {
        if (str_starts_with($src, 'http://') || str_starts_with($src, 'https://')) {
            return $src;
        }
        $base = parse_url($pageUrl);
        if (! isset($base['scheme'], $base['host'])) {
            return null;
        }
        $origin = $base['scheme'].'://'.$base['host'].(isset($base['port']) ? ':'.$base['port'] : '');
        if (str_starts_with($src, '//')) {
            return $base['scheme'].':'.$src;
        }

        // og:image paths are root-relative in practice; anything else is a guess.
        return str_starts_with($src, '/') ? $origin.$src : null;
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
