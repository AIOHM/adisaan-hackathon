<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Tbwim\NewsReader;
use ReflectionMethod;
use Tests\TestCase;

final class NewsOgImageTest extends TestCase
{
    private function ogImage(string $html, string $pageUrl): ?string
    {
        $method = new ReflectionMethod(NewsReader::class, 'ogImage');

        return $method->invoke(new NewsReader, $html, $pageUrl);
    }

    public function test_absolute_og_image_is_used(): void
    {
        $html = '<html><head><meta property="og:image" content="https://cdn.example.test/story.jpg"></head></html>';

        $this->assertSame('https://cdn.example.test/story.jpg', $this->ogImage($html, 'https://example.test/story'));
    }

    public function test_root_relative_og_image_is_made_absolute(): void
    {
        $html = '<html><head><meta content="/img/story.jpg" property="og:image"></head></html>';

        $this->assertSame('https://example.test/img/story.jpg', $this->ogImage($html, 'https://example.test/news/story'));
    }

    public function test_twitter_image_is_the_fallback_and_entities_decode(): void
    {
        $html = '<html><head><meta name="twitter:image" content="https://cdn.example.test/a.jpg?x=1&amp;y=2"></head></html>';

        $this->assertSame('https://cdn.example.test/a.jpg?x=1&y=2', $this->ogImage($html, 'https://example.test/story'));
    }

    public function test_no_meta_image_means_no_picture(): void
    {
        $this->assertNull($this->ogImage('<html><head><title>Plain</title></head></html>', 'https://example.test/story'));
    }

    public function test_first_image_skips_chrome_and_takes_the_photo(): void
    {
        $html = '<img src="/assets/logo.png"><img src="https://cdn.example.test/lead-story.jpg?w=900">';

        $method = new ReflectionMethod(NewsReader::class, 'firstImage');

        $this->assertSame('https://cdn.example.test/lead-story.jpg?w=900', $method->invoke(new NewsReader, $html, 'https://example.test/'));
    }

    public function test_first_image_with_only_chrome_means_no_picture(): void
    {
        $html = '<img src="/img/spacer.gif"><img src="data:image/png;base64,AAAA">';

        $method = new ReflectionMethod(NewsReader::class, 'firstImage');

        $this->assertNull($method->invoke(new NewsReader, $html, 'https://example.test/'));
    }
}
