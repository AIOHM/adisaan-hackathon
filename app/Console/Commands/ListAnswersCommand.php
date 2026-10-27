<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\EchoSubmission;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

final class ListAnswersCommand extends Command
{
    protected $signature = 'tbwim:answers {--waiting : Only answers a person still has to decide on} {--limit=50}';

    protected $description = 'List answers with their receipt, type, review state and text';

    public function handle(): int
    {
        $rows = EchoSubmission::query()
            ->when((bool) $this->option('waiting'), fn ($q) => $q->humans()->where('consent_quote', true)->where('status', '!=', EchoSubmission::STATUS_QUARANTINED)->whereNull('released_at')->where('metadata->review_status', '!=', 'rejected'))
            ->orderByDesc('created_at')->limit(max(1, (int) $this->option('limit')))->get();
        $this->table(['receipt', 'type', 'question', 'state', 'advice', 'text'], $rows->map(fn (EchoSubmission $e) => [
            $e->public_id, $e->isAgent() ? 'agent' : 'human', $e->episode_ref, $e->reviewWord(),
            $e->reviewed_at ? (($e->metadata['review_status'] ?? '').' '.($e->review_reason ?? '')) : '-',
            Str::limit(str_replace("\n", ' ', $this->text($e)), 70),
        ])->all());

        return self::SUCCESS;
    }

    private function text(EchoSubmission $e): string
    {
        $transcript = trim((string) $e->transcript);
        $body = trim((string) $e->body);
        $placeholder = str_starts_with($body, '[Audio perspective') || str_starts_with($body, '[Video perspective');
        if ($transcript !== '' && $placeholder) {
            return '[voice] '.$transcript;
        }

        return (string) $e->body;
    }
}
