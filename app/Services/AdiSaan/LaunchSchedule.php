<?php

declare(strict_types=1);

namespace App\Services\AdiSaan;

use Illuminate\Support\Carbon;

/**
 * What is released, and when.
 *
 * One place decides whether a question's card, its film and its answer are
 * public yet. Before this, "is it published" was a boolean someone flipped by
 * hand; a launch with a daily cadence needs dates, and dates spread across
 * four views are four chances to disagree.
 *
 * Every date is read in Europe/Bucharest, because that is the clock Adrian
 * publishes on and "midnight" has to mean his midnight. Bucharest is UTC+2
 * in November.
 *
 * A story with no `question_release_at` is not scheduled and stays hidden.
 * That is deliberate: questions 4 to 10 have no films yet, and a date would
 * promise a video that does not exist.
 */
final class LaunchSchedule
{
    public const TZ = 'Europe/Bucharest';

    /** Overrides "now", for previewing a future day. Never set in a request. */
    private ?Carbon $at = null;

    /** Look at the schedule as it will be at a given moment. */
    public function at(?Carbon $moment): self
    {
        $clone = new self;
        $clone->at = $moment;

        return $clone;
    }

    public function now(): Carbon
    {
        return $this->at?->copy() ?? Carbon::now(self::TZ);
    }

    /** Is this question's card on the site yet? */
    public function questionReleased(array $story): bool
    {
        return $this->passed($story['question_release_at'] ?? null);
    }

    /**
     * Is this question's film playable yet?
     *
     * Falls back to the card's date, because for every question except the
     * first they arrive together. The first is on the site now with its film
     * counted down, which is the whole shape of a launch.
     */
    public function filmReleased(array $story): bool
    {
        if (empty($story['video'])) {
            return false;
        }

        return $this->passed($story['film_release_at'] ?? $story['question_release_at'] ?? null);
    }

    /** Is AdiSaan's answer — and its why — public yet? */
    public function answerReleased(array $story): bool
    {
        return $this->passed($story['answer_reveal_at'] ?? null);
    }

    /** When the film opens, for a countdown. Null when it already has. */
    public function filmOpensAt(array $story): ?Carbon
    {
        $at = $this->parse($story['film_release_at'] ?? $story['question_release_at'] ?? null);

        return $at !== null && $at->isAfter($this->now()) ? $at : null;
    }

    /** When the answer opens, for a countdown. Null when it already has. */
    public function answerOpensAt(array $story): ?Carbon
    {
        $at = $this->parse($story['answer_reveal_at'] ?? null);

        return $at !== null && $at->isAfter($this->now()) ? $at : null;
    }

    /**
     * The questions a visitor may see, in schedule order.
     *
     * @param  array<int, array<string, mixed>>  $stories
     * @return list<array<string, mixed>>
     */
    public function visibleQuestions(array $stories): array
    {
        $visible = array_values(array_filter(
            $stories,
            fn (array $s): bool => ($s['question_published'] ?? false) === true && $this->questionReleased($s),
        ));

        usort($visible, function (array $a, array $b): int {
            $at = $this->parse($a['question_release_at'] ?? null);
            $bt = $this->parse($b['question_release_at'] ?? null);

            return ($at?->getTimestamp() ?? 0) <=> ($bt?->getTimestamp() ?? 0);
        });

        return $visible;
    }

    /**
     * Everything that opens from this moment on, soonest first — the running
     * order, and what a launch-day check reads.
     *
     * @param  array<int, array<string, mixed>>  $stories
     * @return list<array{at: Carbon, story: string, what: string}>
     */
    public function upcoming(array $stories, int $limit = 20): array
    {
        $events = [];

        foreach ($stories as $story) {
            if (($story['question_published'] ?? false) !== true) {
                continue;
            }

            $id = (string) ($story['id'] ?? '');

            foreach ([
                ['film_release_at', 'question film', ! empty($story['video'])],
                ['answer_reveal_at', 'answer', ! empty($story['answer_video'])],
                ['answer_reveal_at', 'why', ! empty($story['answer_why_video'])],
            ] as [$key, $what, $exists]) {
                $at = $this->parse($story[$key] ?? ($key === 'film_release_at' ? ($story['question_release_at'] ?? null) : null));
                if ($at === null || ! $at->isAfter($this->now())) {
                    continue;
                }

                $events[] = [
                    'at' => $at,
                    'story' => $id,
                    // A date with no file behind it is named as such rather
                    // than counted as something that will appear.
                    'what' => $exists ? $what : $what.' (no file yet)',
                ];
            }
        }

        usort($events, static fn (array $a, array $b): int => $a['at']->getTimestamp() <=> $b['at']->getTimestamp());

        return array_slice($events, 0, $limit);
    }

    private function passed(?string $raw): bool
    {
        $at = $this->parse($raw);

        return $at !== null && ! $at->isAfter($this->now());
    }

    private function parse(?string $raw): ?Carbon
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return null;
        }

        try {
            return Carbon::parse($raw, self::TZ)->setTimezone(self::TZ);
        } catch (\Throwable) {
            return null;
        }
    }
}
