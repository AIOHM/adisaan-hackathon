<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\SignalQuestion;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

final class ListSignalQuestionsCommand extends Command
{
    protected $signature = 'tbwim:signal-questions {--all : Include reviewed drafts} {--limit=50}';

    protected $description = 'List signal-question drafts with their review state';

    public function handle(): int
    {
        $rows = SignalQuestion::query()
            ->when(! (bool) $this->option('all'), fn ($q) => $q->drafts())
            ->orderBy('id')->limit(max(1, (int) $this->option('limit')))->get();
        $this->table(['id', 'state', 'for', 'question', 'signal'], $rows->map(fn (SignalQuestion $q) => [
            $q->id, $q->status, $q->scheduled_for?->toDateString() ?? '—',
            Str::limit(str_replace("\n", ' ', $q->question), 70),
            Str::limit(str_replace("\n", ' ', $q->signal_source.' · '.$q->signal_title), 60),
        ])->all());

        return self::SUCCESS;
    }
}
