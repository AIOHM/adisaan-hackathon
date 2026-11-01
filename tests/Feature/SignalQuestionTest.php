<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\SignalQuestion;
use App\Services\AdiSaan\LaunchSchedule;
use App\Services\Tbwim\Questions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class SignalQuestionTest extends TestCase
{
    use RefreshDatabase;

    private const FEED = 'https://feeds.example.test/wire.xml';

    private const QUESTION = 'What is the best way I imagine to prove a recorded voice is really mine?';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('tbwim_news.feeds', [
            ['url' => self::FEED, 'category' => 'avatars-voice-tech', 'label' => 'Avatar & Video Models'],
        ]);
        config()->set('tbwim.meta.api_key', 'test-key');
    }

    public function test_draft_command_stores_a_draft_from_the_top_signal(): void
    {
        $this->fakeWireAndModel();

        $this->artisan('tbwim:signal-question')->assertSuccessful();

        $draft = SignalQuestion::query()->sole();
        $this->assertSame(SignalQuestion::STATUS_DRAFT, $draft->status);
        $this->assertSame(self::QUESTION, $draft->question);
        $this->assertSame('Voice Clones Learn Three Seconds', $draft->signal_title);
        $this->assertNull($draft->episode_ref);
    }

    public function test_approval_schedules_the_question_for_tomorrow_and_it_opens_then(): void
    {
        $this->travelTo(Carbon::parse('2026-11-01 10:00:00', LaunchSchedule::TZ));
        $draft = $this->makeApprovedDraft();

        $this->assertSame('2026-11-02', $draft->scheduled_for?->toDateString());
        $this->assertStringStartsWith('signal-2026-11-02-', (string) $draft->episode_ref);

        $questions = app(Questions::class);
        $this->assertNull($questions->findOpen($draft->episode_ref));

        $this->travelTo(Carbon::parse('2026-11-02 00:00:01', LaunchSchedule::TZ));
        $this->assertSame($draft->episode_ref, $questions->current()['id']);
        $this->assertSame(self::QUESTION, $questions->current()['question']);
    }

    public function test_news_page_announces_the_approved_brief_and_hides_it_without_one(): void
    {
        $this->fakeWireOnly();
        $this->get('/news')->assertOk()->assertDontSee('AdiSaan&rsquo;s brief', false);

        $this->travelTo(Carbon::parse('2026-11-01 10:00:00', LaunchSchedule::TZ));
        $this->makeApprovedDraft();

        $this->get('/news')->assertOk()
            ->assertSee('AdiSaan&rsquo;s brief', false)
            ->assertSee(self::QUESTION)
            ->assertSee('Voice Clones Learn Three Seconds')
            ->assertSee('Read the brief', false)
            ->assertDontSee('<audio', false);
    }

    public function test_attached_audio_plays_instead_of_the_captions_only_brief(): void
    {
        $this->fakeWireOnly();
        $this->travelTo(Carbon::parse('2026-11-01 10:00:00', LaunchSchedule::TZ));
        $draft = $this->makeApprovedDraft();

        $mp3 = tempnam(sys_get_temp_dir(), 'brief-').'.mp3';
        file_put_contents($mp3, 'fake-audio');
        $this->artisan('tbwim:signal-audio', ['id' => $draft->id, 'file' => $mp3])->assertSuccessful();

        $draft->refresh();
        $this->assertSame('/assets/adisaan/site/briefs/'.$draft->episode_ref.'.mp3', $draft->brief_audio_path);
        $attached = public_path('assets/adisaan/site/briefs/'.$draft->episode_ref.'.mp3');
        $this->assertFileExists($attached);

        try {
            $this->get('/news')->assertOk()
                ->assertSee('<audio data-brief-audio src="/assets/adisaan/site/briefs/'.$draft->episode_ref.'.mp3"', false)
                ->assertSee('Play the brief', false);
        } finally {
            unlink($attached);
            unlink($mp3);
        }
    }

    public function test_drafter_rejects_a_question_outside_the_brand_frame(): void
    {
        Http::fake([
            self::FEED => Http::response($this->rss(), 200, ['Content-Type' => 'application/rss+xml']),
            'api.meta.ai/*' => Http::response(['model' => 'muse-spark-1.3', 'choices' => [['message' => ['content' => '{"signal_index": 0, "question": "How do we prove a voice?", "why": "short", "brief_script": "short"}']]], 'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 20]]),
        ]);

        $this->artisan('tbwim:signal-question')->assertFailed();
        $this->assertSame(0, SignalQuestion::query()->count());
    }

    private function makeApprovedDraft(): SignalQuestion
    {
        $this->fakeWireAndModel();
        $this->artisan('tbwim:signal-question')->assertSuccessful();
        $this->artisan('tbwim:signal-approve', ['id' => 1])->assertSuccessful();

        return SignalQuestion::query()->sole();
    }

    private function fakeWireAndModel(): void
    {
        $brief = "In today's news, voice cloning tools learned to copy a person from three seconds of audio, and two banks already ask customers to speak a passphrase. That worries me, because a voice used to be proof of a person. So tomorrow I ask: ".self::QUESTION.' Bring the way you would trust if the caller was your own child. People will answer in their own words, and agents will answer through their own door, kept apart as always. One honest question, and we see what the world imagines.';
        $why = 'Voice cloning crossed from demo to daily tool this week, and the signal names real products people can already use. The question turns a technology story into a lived one: everyone has a voice, everyone has been asked to trust one, and honest answers will disagree in useful ways.';
        $json = json_encode(['signal_index' => 0, 'question' => self::QUESTION, 'why' => $why, 'brief_script' => $brief]);

        Http::fake([
            self::FEED => Http::response($this->rss(), 200, ['Content-Type' => 'application/rss+xml']),
            'api.meta.ai/*' => Http::response(['model' => 'muse-spark-1.3', 'choices' => [['message' => ['content' => $json]]], 'usage' => ['prompt_tokens' => 800, 'completion_tokens' => 220]]),
        ]);
    }

    private function fakeWireOnly(): void
    {
        Http::fake([
            self::FEED => Http::response($this->rss(), 200, ['Content-Type' => 'application/rss+xml']),
            'www.youtube.com/*' => Http::response('<feed xmlns="http://www.w3.org/2005/Atom"></feed>', 200),
        ]);
    }

    private function rss(): string
    {
        return <<<'XML'
        <?xml version="1.0" encoding="UTF-8"?>
        <rss version="2.0" xmlns:media="http://search.yahoo.com/mrss/">
          <channel>
            <title>Test Wire</title>
            <item>
              <title>Voice Clones Learn Three Seconds</title>
              <link>https://source.example.test/voice-clones</link>
              <pubDate>2026-11-01T08:00:00+00:00</pubDate>
              <description><![CDATA[<p>Cloning tools now copy a voice from three seconds of audio.</p><img src="https://source.example.test/voice.jpg">]]></description>
              <media:thumbnail url="https://source.example.test/voice-thumb.jpg"/>
            </item>
          </channel>
        </rss>
        XML;
    }
}
