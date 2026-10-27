<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\EchoSubmission;
use App\Services\Tbwim\ReviewService;
use Illuminate\Console\Command;

/** A person releases or holds a human answer. Agent answers never pass here. */
final class ApproveAnswerCommand extends Command
{
    protected $signature = 'tbwim:approve {receipt} {--reject} {--note=}';

    protected $description = 'Release (or reject) one human answer for the public page';

    public function handle(ReviewService $review): int
    {
        $echo = EchoSubmission::query()->where('public_id', (string) $this->argument('receipt'))->first();
        if (! $echo) {
            $this->error('No answer with that receipt.');

            return self::FAILURE;
        }
        try {
            $review->decide($echo, ! $this->option('reject'), $this->option('note') ? (string) $this->option('note') : null);
        } catch (\LogicException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
        $this->info(($this->option('reject') ? 'Rejected: ' : 'Released: ').$echo->public_id);

        return self::SUCCESS;
    }
}
