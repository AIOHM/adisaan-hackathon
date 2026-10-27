<?php

declare(strict_types=1);

namespace App\Services\Tbwim;

use App\Services\Meta\MetaClient;
use Illuminate\Support\Facades\Log;

/**
 * Question posters from Muse Image, $0.01 each. A poster lives at
 * public/assets/tbwim/posters/{question-id}.webp and, once present, is
 * what the pages and the agent API show for that question.
 */
final class PosterGenerator
{
    public const DIR = 'assets/tbwim/posters';

    public const EXT = 'webp';

    public function __construct(private readonly MetaClient $meta, private readonly string $baseDir = '') {}

    public static function safeId(string $id): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9][A-Za-z0-9_.-]*$/', $id);
    }

    public function buildPrompt(string $question, string $lead): string
    {
        return strtr((string) config('tbwim.poster.prompt', ''), [':question' => $question, ':lead' => $lead]);
    }

    /** @return array{status: string, path?: string, reason?: string} */
    public function generate(string $id, string $question, string $lead, ?string $prompt = null, bool $force = false): array
    {
        if (! self::safeId($id)) {
            return ['status' => 'failed', 'reason' => 'bad question id'];
        }
        $dir = $this->baseDir !== '' ? $this->baseDir : public_path(self::DIR);
        $file = rtrim($dir, '/').'/'.$id.'.'.self::EXT;
        if (! $force && is_file($file)) {
            return ['status' => 'skipped', 'reason' => 'already exists'];
        }
        try {
            $result = $this->meta->generateImage($prompt ?? $this->buildPrompt($question, $lead));
        } catch (\Throwable $e) {
            Log::warning('tbwim.poster.failed', ['question' => $id, 'error' => $e->getMessage()]);

            return ['status' => 'failed', 'reason' => $e->getMessage()];
        }
        $bin = base64_decode($result['b64'], true);
        if ($bin === false || strlen($bin) < 100) {
            return ['status' => 'failed', 'reason' => 'unusable image data'];
        }
        if (! is_dir($dir) && ! @mkdir($dir, 0755, true)) {
            return ['status' => 'failed', 'reason' => 'cannot create poster dir'];
        }
        if (file_put_contents($file, $bin) === false) {
            @unlink($file);

            return ['status' => 'failed', 'reason' => 'cannot write poster file'];
        }

        return ['status' => 'done', 'path' => $file];
    }
}
