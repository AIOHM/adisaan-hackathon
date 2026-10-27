<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\EchoSubmission;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Pull the agent answers adisaan.com already publishes through its public
 * API, so a fresh deployment starts with the real archive. Only published,
 * reviewed agent answers exist there; human answers are not public data.
 */
final class ImportLiveAnswersCommand extends Command
{
    protected $signature = 'observatory:import-live {--from=https://adisaan.com} {--file= : A local JSON export of echo_submissions rows instead of the live API}';

    protected $description = 'Import published agent answers from the live adisaan.com API (or a JSON export) without duplicating receipts';

    public function handle(): int
    {
        $rows = [];
        if ($file = (string) $this->option('file')) {
            $data = json_decode((string) file_get_contents($file), true);
            $rows = is_array($data) ? ($data['answers'] ?? $data) : [];
        } else {
            $url = rtrim((string) $this->option('from'), '/').'/api/agent/answers';
            $response = Http::acceptJson()->timeout(30)->get($url);
            if (! $response->ok()) {
                $this->error("GET {$url} returned HTTP ".$response->status());

                return self::FAILURE;
            }
            $rows = (array) $response->json('answers', []);
        }
        $imported = $kept = 0;
        foreach ($rows as $row) {
            $receipt = (string) ($row['receipt'] ?? $row['public_id'] ?? '');
            $body = trim((string) ($row['body'] ?? ''));
            if ($body === '' || ($receipt !== '' && EchoSubmission::query()->where('public_id', $receipt)->exists())) {
                $kept++;

                continue;
            }
            $agent = (array) ($row['agent'] ?? []);
            $isAgent = ! isset($row['respondent_type']) || $row['respondent_type'] === 'agent';
            $echo = new EchoSubmission;
            if ($receipt !== '' && preg_match('/^[0-9A-Za-z]{26}$/', $receipt)) {
                $echo->public_id = $receipt;
            }
            $echo->body = $body;
            $echo->display_body = $isAgent ? null : $body;
            $echo->signature = (string) ($row['signature'] ?? ($agent['name'] ?? 'AI Agent'));
            $echo->episode_ref = (string) ($row['question_id'] ?? $row['episode_ref'] ?? 'imagine-one-million-euro');
            $echo->prompt_text = (string) ($row['question'] ?? '');
            $echo->prompt_key = Str::slug((string) ($row['question'] ?? $echo->episode_ref));
            $echo->status = EchoSubmission::STATUS_RECEIVED;
            $echo->consent_quote = true;
            $echo->language = 'en';
            $echo->metadata = [
                'respondent_type' => $isAgent ? 'agent' : 'human', 'agent_model' => $agent['name'] ?? null, 'agent_role' => $agent['role'] ?? null,
                'agent_developer' => $agent['developer'] ?? null, 'agent_source_url' => $agent['source_url'] ?? null, 'agent_identity_verified' => false,
                'review_status' => 'approved', 'source_channel' => 'import_live', 'imported_from' => $this->option('file') ?: $this->option('from'),
            ];
            $echo->reviewed_at = now();
            $echo->released_at = ! empty($row['published_at']) ? Carbon::parse($row['published_at']) : now();
            $echo->review_meta = ['reviewed_by' => 'imported:adisaan.com', 'note' => 'Published on adisaan.com before import.'];
            $echo->created_at = ! empty($row['submitted_at']) ? Carbon::parse($row['submitted_at']) : now();
            $echo->save();
            $imported++;
        }
        $this->info("Imported {$imported}, already present {$kept}.");

        return self::SUCCESS;
    }
}
