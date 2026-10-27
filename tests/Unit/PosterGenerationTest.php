<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Meta\MetaClient;
use App\Services\Tbwim\PosterGenerator;
use App\Services\Tbwim\Questions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class PosterGenerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_generator_saves_the_poster_and_settles_one_image(): void
    {
        config()->set('tbwim.meta.api_key', 'test-key');
        config()->set('tbwim.meta.monthly_limit_usd', 5);
        $bin = str_repeat('w', 200);
        Http::fake(['api.meta.ai/*' => Http::response(['data' => [['b64_json' => base64_encode($bin)]]])]);
        $dir = sys_get_temp_dir().'/tbwim-poster-test-'.uniqid();
        $generator = new PosterGenerator(app(MetaClient::class), $dir);

        $result = $generator->generate('imagine-one-million-euro', 'Q?', 'Lead');
        $this->assertSame('done', $result['status']);
        $this->assertSame($bin, (string) file_get_contents($dir.'/imagine-one-million-euro.webp'));
        $this->assertStringContainsString('imagination', $generator->buildPrompt('Q?', 'Lead'));
        $this->assertSame('skipped', $generator->generate('imagine-one-million-euro', 'Q?', 'Lead')['status']);
        $this->assertSame('done', $generator->generate('imagine-one-million-euro', 'Q?', 'Lead', null, true)['status']);
        $this->assertSame('failed', $generator->generate('../evil', 'Q?', 'Lead')['status']);
        $this->assertSame(20000, (int) DB::table('meta_api_budgets')->value('committed_micros'));

        unlink($dir.'/imagine-one-million-euro.webp');
        rmdir($dir);
    }

    public function test_generator_refuses_unusable_image_data(): void
    {
        config()->set('tbwim.meta.api_key', 'test-key');
        config()->set('tbwim.meta.monthly_limit_usd', 5);
        Http::fake(['api.meta.ai/*' => Http::response(['data' => [['b64_json' => '!!!not-base64!!!']]])]);
        $dir = sys_get_temp_dir().'/tbwim-poster-test-'.uniqid();
        $generator = new PosterGenerator(app(MetaClient::class), $dir);

        $this->assertSame('failed', $generator->generate('q1', 'Q?', 'Lead')['status']);
        $this->assertFileDoesNotExist($dir.'/q1.webp');
    }

    public function test_poster_command_and_catalogue_override(): void
    {
        config()->set('tbwim.meta.api_key', 'test-key');
        config()->set('tbwim.meta.monthly_limit_usd', 5);
        Http::fake(['api.meta.ai/*' => Http::response(['data' => [['b64_json' => base64_encode(str_repeat('w', 200))]]])]);
        $dir = sys_get_temp_dir().'/tbwim-poster-test-'.uniqid();
        app()->bind(PosterGenerator::class, fn (): PosterGenerator => new PosterGenerator(app(MetaClient::class), $dir));

        $this->artisan('tbwim:poster', ['question' => 'imagine-one-million-euro'])->assertSuccessful();
        $this->assertFileExists($dir.'/imagine-one-million-euro.webp');

        $publicDir = public_path(PosterGenerator::DIR);
        @mkdir($publicDir, 0755, true);
        try {
            file_put_contents($publicDir.'/probe-question.webp', 'x');
            $url = app(Questions::class)->generatedPoster('probe-question');
            $this->assertSame(rtrim((string) config('tbwim.url'), '/').'/assets/tbwim/posters/probe-question.webp', $url);
            $this->assertNull(app(Questions::class)->generatedPoster('no-such-poster'));
            $this->assertNull(app(Questions::class)->generatedPoster('../evil'));
        } finally {
            @unlink($publicDir.'/probe-question.webp');
            @rmdir($publicDir);
        }

        unlink($dir.'/imagine-one-million-euro.webp');
        rmdir($dir);
    }
}
