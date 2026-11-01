<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\SignalQuestion;
use App\Services\AdiSaan\LaunchSchedule;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

final class ApproveSignalQuestionCommand extends Command
{
    protected $signature = 'tbwim:signal-approve {id} {--reject} {--for= : Schedule for this date (Y-m-d, Europe/Bucharest) instead of tomorrow}';

    protected $description = "Approve (or reject) a signal-question draft; approval schedules it as tomorrow's question";

    public function handle(): int
    {
        $row = SignalQuestion::query()->find($this->argument('id'));
        if (! $row) {
            $this->error('No signal question with that id.');

            return self::FAILURE;
        }
        if ($row->status !== SignalQuestion::STATUS_DRAFT) {
            $this->error('That draft is already decided: '.$row->status.'.');

            return self::FAILURE;
        }

        if ((bool) $this->option('reject')) {
            $row->status = SignalQuestion::STATUS_REJECTED;
            $row->reviewed_at = now();
            $row->save();
            $this->info('Rejected: '.$row->id);

            return self::SUCCESS;
        }

        $for = (string) ($this->option('for') ?? '');
        $date = $for !== ''
            ? Carbon::parse($for, LaunchSchedule::TZ)->startOfDay()
            : Carbon::now(LaunchSchedule::TZ)->addDay()->startOfDay();

        $base = 'signal-'.$date->format('Y-m-d').'-'.Str::slug(Str::limit((string) Str::of($row->question)->after('What is the best way I imagine'), 40, ''));
        $ref = $base;
        for ($n = 2; SignalQuestion::query()->where('episode_ref', $ref)->exists(); $n++) {
            $ref = $base.'-'.$n;
        }

        $row->status = SignalQuestion::STATUS_APPROVED;
        $row->episode_ref = $ref;
        $row->scheduled_for = $date->toDateString();
        $row->reviewed_at = now();
        $row->save();

        $this->info("Approved: {$row->id} as {$ref} for {$date->toDateString()}");
        $this->line('Question: '.$row->question);

        return self::SUCCESS;
    }
}
