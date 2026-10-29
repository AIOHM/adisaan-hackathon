<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\FounderThought;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

final class ListThoughtsCommand extends Command
{
    protected $signature = 'tbwim:thoughts {--all : Include reviewed drafts} {--limit=50}';

    protected $description = 'List distilled founder thoughts with their review state';

    public function handle(): int
    {
        $rows = FounderThought::query()
            ->when(! (bool) $this->option('all'), fn ($q) => $q->drafts())
            ->orderBy('id')->limit(max(1, (int) $this->option('limit')))->get();
        $this->table(['id', 'source', 'state', 'question', 'answer', 'why'], $rows->map(fn (FounderThought $t) => [
            $t->id, $t->source_type, $t->status,
            Str::limit(str_replace("\n", ' ', $t->question), 60),
            Str::limit(str_replace("\n", ' ', $t->answer), 60),
            Str::limit(str_replace("\n", ' ', $t->why_valid), 60),
        ])->all());

        return self::SUCCESS;
    }
}
