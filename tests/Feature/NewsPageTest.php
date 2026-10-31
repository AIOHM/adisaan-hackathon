<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class NewsPageTest extends TestCase
{
    use RefreshDatabase;

    private const FEED_AVATARS = 'https://feeds.example.test/avatars.xml';

    private const FEED_CREATOR = 'https://feeds.example.test/creator.xml';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('tbwim_news.feeds', [
            ['url' => self::FEED_AVATARS, 'category' => 'avatars-voice-tech', 'label' => 'Avatars & Voice Tech'],
            ['url' => self::FEED_CREATOR, 'category' => 'creator-journey-tips', 'label' => 'Creator Tips'],
        ]);
    }

    public function test_news_page_renders_the_dispatch_with_lead_videos_and_grid(): void
    {
        $this->fakeGoodFeeds();
        $this->get('/news')->assertOk()
            ->assertSee('The Avatar Lab Dispatch', false)
            ->assertSee('2 Signals Tracked', false)
            ->assertSee('Avatar Engine X Ships Expressive Voices', false)
            ->assertSee('Cut Your Edit Time In Half', false)
            ->assertSee('AdiSaan Video Chronicles', false)
            ->assertSee('TEST-VIDEO-ONE', false)
            ->assertSee('Fast Signals', false)
            ->assertSee('The Curator’s Lens', false)
            ->assertSee('Follow The Build', false)
            ->assertSee('Intelligence Streams', false)
            ->assertSee('news-dispatch.css', false);
    }

    public function test_dead_youtube_feed_falls_back_to_known_videos(): void
    {
        Http::fake([
            self::FEED_AVATARS => Http::response($this->rss('Avatar Engine X Ships Expressive Voices', 'avatar-engine-x', '2026-10-30T10:00:00+00:00'), 200),
            self::FEED_CREATOR => Http::response($this->rss('Cut Your Edit Time In Half', 'edit-faster', '2026-10-29T10:00:00+00:00'), 200),
            'www.youtube.com/*' => Http::response('gone', 404),
        ]);

        $this->get('/news')->assertOk()
            ->assertSee('AdiSaan Video Chronicles', false)
            ->assertSee('When no one is looking, what do you see?', false);
    }

    public function test_category_filter_narrows_the_signals(): void
    {
        $this->fakeGoodFeeds();
        $this->get('/news?category=creator-journey-tips')->assertOk()
            ->assertSee('Cut Your Edit Time In Half', false)
            ->assertDontSee('Avatar Engine X Ships Expressive Voices', false);
        $this->get('/news?category=no-such-category')->assertNotFound();
    }

    public function test_search_hides_the_lead_and_matches_words(): void
    {
        $this->fakeGoodFeeds();
        $this->get('/news?q=expressive')->assertOk()
            ->assertSee('Avatar Engine X Ships Expressive Voices', false)
            ->assertDontSee('Cut Your Edit Time In Half', false)
            ->assertDontSee('Lead Story', false);
    }

    public function test_broken_feeds_render_an_honest_page_instead_of_a_500(): void
    {
        Http::fake([
            '*' => Http::response('not xml at all', 500),
        ]);

        $this->get('/news')->assertOk()
            ->assertSee('The Avatar Lab Dispatch', false)
            ->assertSee('No signals on this wire right now', false)
            ->assertSee('retrying', false);
    }

    public function test_empty_feed_list_still_serves_the_page(): void
    {
        $this->fakeGoodFeeds();
        config()->set('tbwim_news.feeds', []);

        $this->get('/news')->assertOk()
            ->assertSee('The Avatar Lab Dispatch', false)
            ->assertSee('0 Signals Tracked', false)
            ->assertSee('AdiSaan Video Chronicles', false);
    }

    public function test_menu_links_news_locally_and_sitemap_lists_it(): void
    {
        $this->get('/')->assertOk()->assertSee('href="'.route('adisaan.public.news').'"', false);
        $this->get('/sitemap.xml')->assertOk()->assertSee(config('tbwim.url').'/news', false);
    }

    private function fakeGoodFeeds(): void
    {
        Http::fake([
            self::FEED_AVATARS => Http::response($this->rss('Avatar Engine X Ships Expressive Voices', 'avatar-engine-x', '2026-10-30T10:00:00+00:00'), 200, ['Content-Type' => 'application/rss+xml']),
            self::FEED_CREATOR => Http::response($this->rss('Cut Your Edit Time In Half', 'edit-faster', '2026-10-29T10:00:00+00:00'), 200, ['Content-Type' => 'application/rss+xml']),
            'www.youtube.com/*' => Http::response($this->youtube(), 200, ['Content-Type' => 'application/atom+xml']),
        ]);
    }

    private function rss(string $title, string $slug, string $date): string
    {
        return <<<XML
        <?xml version="1.0" encoding="UTF-8"?>
        <rss version="2.0" xmlns:media="http://search.yahoo.com/mrss/">
          <channel>
            <title>Test Feed</title>
            <item>
              <title>{$title}</title>
              <link>https://source.example.test/{$slug}</link>
              <pubDate>{$date}</pubDate>
              <description><![CDATA[<p>An excerpt about {$title} with enough words to render.</p><img src="https://source.example.test/{$slug}.jpg">]]></description>
              <media:thumbnail url="https://source.example.test/{$slug}-thumb.jpg"/>
            </item>
          </channel>
        </rss>
        XML;
    }

    private function youtube(): string
    {
        return <<<'XML'
        <?xml version="1.0" encoding="UTF-8"?>
        <feed xmlns="http://www.w3.org/2005/Atom" xmlns:yt="http://www.youtube.com/xml/schemas/2015">
          <entry>
            <yt:videoId>TESTVIDEOID1</yt:videoId>
            <title>TEST-VIDEO-ONE</title>
            <link rel="alternate" href="https://www.youtube.com/watch?v=TESTVIDEOID1"/>
            <published>2026-10-13T10:00:00+00:00</published>
          </entry>
        </feed>
        XML;
    }
}
