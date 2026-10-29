<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\FounderThought;
use Illuminate\Console\Command;

final class ApproveThoughtCommand extends Command
{
    protected $signature = 'tbwim:thought-approve {id} {--reject}';

    protected $description = 'Approve (or reject) one distilled founder thought for the dataset';

    public function handle(): int
    {
        $thought = FounderThought::query()->find($this->argument('id'));
        if (! $thought) {
            $this->error('No thought with that id.');

            return self::FAILURE;
        }
        if ($thought->status !== FounderThought::STATUS_DRAFT) {
            $this->error('That thought is already decided: '.$thought->status.'.');

            return self::FAILURE;
        }
        $thought->status = $this->option('reject') ? FounderThought::STATUS_REJECTED : FounderThought::STATUS_APPROVED;
        $thought->reviewed_at = now();
        $thought->save();
        $this->info(($this->option('reject') ? 'Rejected: ' : 'Approved: ').$thought->id);

        return self::SUCCESS;
    }
}
