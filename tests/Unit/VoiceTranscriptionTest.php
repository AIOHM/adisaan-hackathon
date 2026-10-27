<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\EchoSubmission;
use App\Services\Meta\MetaApiBudget;
use App\Services\Meta\MetaClient;
use App\Services\Tbwim\VoiceTranscriber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class VoiceTranscriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_audio_ledger_reserves_the_ten_minute_worst_case_and_settles_whole_seconds(): void
    {
        $this->assertSame(30000, MetaApiBudget::reservationForAudio());
        $this->assertSame(3050, MetaApiBudget::costForAudio(61500));
        $this->assertSame(30000, MetaApiBudget::costForAudio(3600000));

        $budget = app(MetaApiBudget::class);
        $period = $budget->reserve(1000000, MetaApiBudget::reservationForAudio());
        $budget->settleAudio($period, 61500, MetaApiBudget::reservationForAudio());
        $this->assertSame(3050, (int) DB::table('meta_api_budgets')->where('period', $period)->value('committed_micros'));

        $period2 = $budget->reserve(1000000, MetaApiBudget::reservationForAudio());
        $budget->settleAudio($period2, null, MetaApiBudget::reservationForAudio());
        $this->assertSame(3050 + 30000, (int) DB::table('meta_api_budgets')->where('period', $period2)->value('committed_micros'));
    }

    public function test_transcriber_stores_the_transcript_and_leaves_the_body_alone(): void
    {
        config()->set('tbwim.meta.api_key', 'test-key');
        config()->set('tbwim.meta.monthly_limit_usd', 5);
        Http::fake(['api.meta.ai/*' => Http::response(['sessionId' => 's1', 'transcript' => 'Start before you feel ready.', 'audioDurationMs' => 12000, 'turns' => []])]);
        Process::fake();
        Storage::fake('local');
        Storage::disk('local')->put('echo-media/clip.mp3', 'fake-audio');

        $echo = $this->mediaAnswer();
        $wav = tempnam(sys_get_temp_dir(), 'tbwim-test-');
        file_put_contents($wav, 'RIFFfake');
        $transcriber = new VoiceTranscriber(app(MetaClient::class), fn (string $in): ?string => $wav);

        $this->assertSame(['status' => 'done'], $transcriber->transcribe($echo));
        $echo->refresh();
        $this->assertSame('Start before you feel ready.', $echo->transcript);
        $this->assertSame('[Audio perspective awaiting review]', $echo->body);
        $this->assertSame('done', $echo->metadata['voice']['status']);
        $this->assertSame('muse-voice-transcribe-1.0', $echo->metadata['voice']['model']);
        $this->assertNull($echo->released_at, 'transcription never releases');
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/v1/asr/transcribe'));
        $this->assertFileDoesNotExist($wav, 'the converted clip is cleaned up');
    }

    public function test_transcriber_skips_without_media_or_transcript_and_without_the_converter(): void
    {
        config()->set('tbwim.meta.api_key', 'test-key');
        config()->set('tbwim.meta.monthly_limit_usd', 5);
        Http::fake(['api.meta.ai/*' => Http::response(['transcript' => 'x', 'audioDurationMs' => 1000, 'turns' => []])]);
        Process::fake();
        Storage::fake('local');

        $plain = EchoSubmission::create(['body' => 'typed answer', 'episode_ref' => 'imagine-one-million-euro', 'status' => 'received', 'consent_quote' => true, 'metadata' => ['respondent_type' => 'human']]);
        $this->assertSame('skipped', app(VoiceTranscriber::class)->transcribe($plain)['status']);
        Http::assertSentCount(0);

        Process::fake(['*' => Process::result(exitCode: 1)]);
        Storage::disk('local')->put('echo-media/clip.mp3', 'fake-audio');
        $echo = $this->mediaAnswer();
        $result = app(VoiceTranscriber::class)->transcribe($echo);
        $this->assertSame('unavailable', $result['status']);
        $this->assertNull($echo->fresh()->transcript);
        Http::assertSentCount(0);
    }

    public function test_transcriber_refuses_clips_over_ten_minutes(): void
    {
        config()->set('tbwim.meta.api_key', 'test-key');
        config()->set('tbwim.meta.monthly_limit_usd', 5);
        Http::fake(['api.meta.ai/*' => Http::response(['transcript' => 'x', 'audioDurationMs' => 1000, 'turns' => []])]);
        Process::fake(['*' => Process::result(output: '601.5')]);
        Storage::fake('local');
        Storage::disk('local')->put('echo-media/long.mp3', 'fake-audio');

        $echo = $this->mediaAnswer('echo-media/long.mp3');
        $this->assertSame('too_long', app(VoiceTranscriber::class)->transcribe($echo)['status']);
        Http::assertSentCount(0);
    }

    public function test_pending_command_transcribes_and_the_waiting_list_shows_voice(): void
    {
        config()->set('tbwim.meta.api_key', 'test-key');
        config()->set('tbwim.meta.monthly_limit_usd', 5);
        Http::fake(['api.meta.ai/*' => Http::response(['sessionId' => 's1', 'transcript' => 'Begin anyway.', 'audioDurationMs' => 8000, 'turns' => []])]);
        Process::fake();
        Storage::fake('local');
        Storage::disk('local')->put('echo-media/clip.mp3', 'fake-audio');
        $wav = tempnam(sys_get_temp_dir(), 'tbwim-test-');
        file_put_contents($wav, 'RIFFfake');
        app()->bind(VoiceTranscriber::class, fn (): VoiceTranscriber => new VoiceTranscriber(app(MetaClient::class), fn (string $in): ?string => $wav));

        $echo = $this->mediaAnswer();
        $this->artisan('tbwim:transcribe-pending')->assertSuccessful();
        $this->assertSame('Begin anyway.', $echo->fresh()->transcript);
        $this->artisan('tbwim:answers', ['--waiting' => true])->assertSuccessful();
    }

    private function mediaAnswer(string $path = 'echo-media/clip.mp3'): EchoSubmission
    {
        return EchoSubmission::create(['body' => '[Audio perspective awaiting review]', 'episode_ref' => 'imagine-one-million-euro', 'status' => 'received', 'consent_quote' => false,
            'metadata' => ['respondent_type' => 'human', 'media' => ['disk' => 'local', 'path' => $path, 'mime' => 'audio/mpeg', 'size' => 10]]]);
    }
}
