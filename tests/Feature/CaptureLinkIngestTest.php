<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\ExtractThought;
use App\Jobs\IngestCaptureLink;
use App\Jobs\TranscribeVoiceAnswer;
use App\Models\EchoSubmission;
use App\Models\FounderThought;
use App\Services\Tbwim\DocumentTextExtractor;
use App\Services\Tbwim\LinkIngestor;
use App\Services\Tbwim\ThoughtExtractor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class CaptureLinkIngestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('tbwim.capture.intake_secret', 'test-secret');
        config()->set('tbwim.capture.founder_ids', ['123']);
        Queue::fake();
    }

    public function test_a_link_only_message_is_stored_as_a_link_capture(): void
    {
        $response = $this->postJson('/api/capture/thought', [
            'text' => 'https://example.com/some-talk', 'from_id' => '123', 'message_id' => '11',
        ], $this->auth())->assertCreated();

        $echo = EchoSubmission::query()->where('public_id', $response->json('receipt'))->firstOrFail();
        $this->assertSame('https://example.com/some-talk', $echo->metadata['link']['url']);
        $this->assertFalse($echo->consent_quote);
        Queue::assertPushed(IngestCaptureLink::class);
        Queue::assertNotPushed(ExtractThought::class);
    }

    public function test_a_webpage_link_becomes_words_then_a_distillation(): void
    {
        Http::fake(['example.com/*' => Http::response(
            '<html><head><title>On starting small</title></head><body><article><p>'.str_repeat('Start small and let each finished piece teach the next one. ', 8).'</p></article></body></html>'
        )]);
        $response = $this->postJson('/api/capture/thought', [
            'url' => 'https://example.com/start-small', 'from_id' => '123', 'message_id' => '12',
        ], $this->auth())->assertCreated();

        (new IngestCaptureLink((string) $response->json('receipt')))->handle(app(LinkIngestor::class));

        $echo = EchoSubmission::query()->where('public_id', $response->json('receipt'))->firstOrFail();
        $this->assertSame('read', $echo->metadata['link']['status']);
        $this->assertSame('On starting small', $echo->metadata['link']['title']);
        $this->assertStringContainsString('Start small', $echo->body);
        Queue::assertPushed(ExtractThought::class);
    }

    public function test_an_unreadable_link_is_recorded_as_failed_never_distilled(): void
    {
        Http::fake(['example.com/*' => Http::response('<html><body><p>tiny</p></body></html>')]);
        $response = $this->postJson('/api/capture/thought', [
            'url' => 'https://example.com/thin', 'from_id' => '123', 'message_id' => '13',
        ], $this->auth())->assertCreated();

        (new IngestCaptureLink((string) $response->json('receipt')))->handle(app(LinkIngestor::class));

        $echo = EchoSubmission::query()->where('public_id', $response->json('receipt'))->firstOrFail();
        $this->assertSame('failed', $echo->metadata['link']['status']);
        $this->assertNotEmpty($echo->metadata['link']['reason']);
        Queue::assertNotPushed(ExtractThought::class);
    }

    public function test_a_private_address_link_is_refused_before_any_fetch(): void
    {
        Http::fake();
        try {
            app(LinkIngestor::class)->ingest('http://127.0.0.1:8000/admin');
            $this->fail('A loopback link must be refused.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('private or reserved', $e->getMessage());
        }
        Http::assertNothingSent();
    }

    public function test_a_youtube_link_gives_its_captions(): void
    {
        $player = json_encode(['captions' => ['playerCaptionsTracklistRenderer' => ['captionTracks' => [
            ['languageCode' => 'en', 'baseUrl' => 'https://www.youtube.com/api/timedtext?v=abc123&lang=en'],
        ]]]]);
        $events = json_encode(['events' => [
            ['segs' => [['utf8' => 'The best teams start before they feel ready ']]],
            ['segs' => [['utf8' => 'and learn in public every single week without fail.']]],
            ['segs' => [['utf8' => str_repeat('Momentum compounds when the work is visible. ', 3)]]],
        ]]);
        Http::fake([
            'www.youtube.com/watch*' => Http::response('<html><script>ytInitialPlayerResponse = '.$player.';</script></html>'),
            'www.youtube.com/api/timedtext*' => Http::response($events),
        ]);

        $result = app(LinkIngestor::class)->ingest('https://www.youtube.com/watch?v=abc123');

        $this->assertSame('text', $result['kind']);
        $this->assertStringContainsString('start before they feel ready', $result['text']);
    }

    public function test_media_links_are_handed_to_transcription_not_scraped(): void
    {
        Http::fake(['example.com/*' => Http::response(
            '<html><head><meta property="og:video" content="https://cdn.example.com/talk.mp4"><title>Talk</title></head><body>x</body></html>'
        )]);

        $page = app(LinkIngestor::class)->ingest('https://example.com/watch-talk');
        $this->assertSame(['kind' => 'media', 'media_url' => 'https://cdn.example.com/talk.mp4', 'media_ext' => 'mp4'], $page);

        $direct = app(LinkIngestor::class)->ingest('https://example.com/episode.mp3');
        $this->assertSame('media', $direct['kind']);
        $this->assertSame('mp3', $direct['media_ext']);
    }

    public function test_a_text_document_upload_is_read_and_distilled(): void
    {
        $file = UploadedFile::fake()->createWithContent('idea.txt', str_repeat('Write the first version badly, then improve it in public. ', 4));
        $response = $this->post('/api/capture/thought', [
            'file' => $file, 'from_id' => '123', 'message_id' => '14',
        ], $this->auth())->assertCreated();

        $echo = EchoSubmission::query()->where('public_id', $response->json('receipt'))->firstOrFail();
        $this->assertSame('idea.txt', $echo->metadata['document']['name']);
        $this->assertStringContainsString('first version badly', $echo->body);
        Queue::assertPushed(ExtractThought::class);
        Queue::assertNotPushed(TranscribeVoiceAnswer::class);
    }

    public function test_an_unsupported_file_is_refused(): void
    {
        $file = UploadedFile::fake()->createWithContent('payload.exe', 'MZ');
        $this->post('/api/capture/thought', [
            'file' => $file, 'from_id' => '123', 'message_id' => '15',
        ], $this->auth())->assertStatus(422);
        $this->assertSame(0, EchoSubmission::query()->count());
    }

    public function test_docx_words_are_extracted(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'tbwim-docx-').'.docx';
        $zip = new \ZipArchive;
        $zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString('word/document.xml', '<?xml version="1.0"?><w:document><w:body><w:p><w:r><w:t>'.str_repeat('Ship the small honest version first, then listen hard. ', 4).'</w:t></w:r></w:p></w:body></w:document>');
        $zip->close();

        $text = app(DocumentTextExtractor::class)->extract($path, 'docx');
        @unlink($path);

        $this->assertStringContainsString('small honest version', $text);
    }

    public function test_pdf_words_are_extracted_when_the_host_can_read_them(): void
    {
        if (! \Illuminate\Support\Facades\Process::run('command -v pdftotext')->successful()) {
            $this->markTestSkipped('pdftotext is not installed on this host.');
        }
        $words = 'Ship the small honest version first, then listen hard every single week.';
        $stream = "BT /F1 18 Tf 72 720 Td ({$words}) Tj ET";
        $pdf = "%PDF-1.4\n1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj\n2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj\n"
            ."3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >> endobj\n"
            ."4 0 obj << /Length ".strlen($stream)." >> stream\n{$stream}\nendstream endobj\n"
            ."5 0 obj << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF";
        $path = tempnam(sys_get_temp_dir(), 'tbwim-pdf-').'.pdf';
        file_put_contents($path, $pdf);

        $text = app(DocumentTextExtractor::class)->extract($path, 'pdf');
        @unlink($path);

        $this->assertStringContainsString('small honest version', $text);
    }

    public function test_distilled_drafts_remember_their_source(): void
    {
        config()->set('tbwim.meta.api_key', 'test-key');
        Http::fake(['api.meta.ai/*' => Http::response(['model' => 'muse-spark-1.3', 'choices' => [['message' => ['content' => json_encode([
            'question' => 'What is the best way I imagine to compound fastest?',
            'answer' => implode(' ', array_fill(0, 40, 'patient')),
            'why_valid' => implode(' ', array_fill(0, 40, 'grounded')),
        ])]]], 'usage' => ['prompt_tokens' => 500, 'completion_tokens' => 60]])]);

        foreach (['link' => ['link' => ['url' => 'https://example.com/x', 'status' => 'read']], 'document' => ['document' => ['name' => 'a.txt', 'ext' => 'txt']]] as $source => $extra) {
            $echo = EchoSubmission::create([
                'episode_ref' => 'founder-capture', 'body' => 'Words worth distilling from '.$source.'.',
                'status' => 'received', 'metadata' => ['founder_capture' => true] + $extra,
            ]);
            (new ExtractThought($echo->public_id))->handle(app(ThoughtExtractor::class));
            $this->assertSame($source, FounderThought::query()->where('echo_submission_id', $echo->id)->firstOrFail()->source_type);
        }
    }

    /** @return array<string, string> */
    private function auth(): array
    {
        return ['Authorization' => 'Bearer test-secret'];
    }
}
