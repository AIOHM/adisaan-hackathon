<?php

declare(strict_types=1);

namespace App\Services\Tbwim;

use App\Models\SignalQuestion;
use App\Services\AdiSaan\LaunchSchedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * The question catalogue.
 *
 * One list for the pages, the agent API and the console commands: the
 * stories in `config/adisaan_public.php` that are published, in the order
 * the launch schedule releases them. A question is open once its release
 * moment has passed. `config/tbwim.php` keeps a short catalogue as the
 * fallback for an install without the site config.
 */
final class Questions
{
    public function __construct(private readonly LaunchSchedule $schedule) {}

    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        $base = (string) config('tbwim.media_base');
        $stories = array_values(array_filter(
            (array) config('adisaan_public.stories', []),
            static fn (array $s): bool => ($s['question_published'] ?? false) === true && ! empty($s['id']),
        ));
        // Approved signal questions join the catalogue as ordinary stories:
        // tomorrow they are simply the newest question, here and on the
        // agent API. Unapproved drafts never reach this list.
        if (Schema::hasTable('signal_questions')) {
            foreach (SignalQuestion::approved()->get() as $signal) {
                if ($signal->episode_ref === null || $signal->scheduled_for === null) {
                    continue;
                }
                $stories[] = [
                    'id' => $signal->episode_ref,
                    'question' => $signal->question,
                    'lead' => $signal->question,
                    'question_release_at' => $signal->scheduled_for->toDateString(),
                    'life_area' => 'signal',
                    'tag' => '#TBWIMSignal',
                ];
            }
        }
        if ($stories === []) {
            return $this->fallback($base);
        }

        usort($stories, function (array $a, array $b): int {
            $at = $this->parse($a['question_release_at'] ?? null);
            $bt = $this->parse($b['question_release_at'] ?? null);

            return ($at?->getTimestamp() ?? PHP_INT_MAX) <=> ($bt?->getTimestamp() ?? PHP_INT_MAX);
        });

        $out = [];
        foreach ($stories as $i => $s) {
            $q = [
                'id' => (string) $s['id'],
                'number' => $i + 1,
                'question' => (string) ($s['question'] ?? $s['lead'] ?? ''),
                'lead' => (string) ($s['lead'] ?? $s['question'] ?? ''),
                'life_area' => $s['life_area'] ?? null,
                'tag' => $s['tag'] ?? null,
                'opens_at' => $s['question_release_at'] ?? null,
                'video' => $s['video'] ?? null,
                'poster' => $s['poster'] ?? null,
            ];
            foreach (['video', 'poster'] as $key) {
                if (! empty($q[$key]) && str_starts_with((string) $q[$key], '/')) {
                    $q[$key] = $base.$q[$key];
                }
            }
            if (($local = $this->generatedPoster($q['id'])) !== null) {
                $q['poster'] = $local;
            }
            $q['open'] = $this->schedule->questionReleased($s);
            $out[] = $q;
        }

        return $out;
    }

    /** @return list<array<string, mixed>> */
    public function open(): array
    {
        return array_values(array_filter($this->all(), static fn (array $q): bool => $q['open']));
    }

    /** The newest open question, or the first one listed. */
    public function current(): array
    {
        $current = null;
        $currentAt = null;
        foreach ($this->open() as $q) {
            $at = $this->parse($q['opens_at']) ?? Carbon::createFromTimestamp(0);
            // Newest open wins; on a tie the later catalogue entry wins, so
            // an approved signal question (appended last) takes its day even
            // when a config story opens the same midnight.
            if ($currentAt === null || $at->gte($currentAt)) {
                $current = $q;
                $currentAt = $at;
            }
        }

        return $current ?? ($this->all()[0] ?? ['id' => 'none', 'question' => '', 'number' => 0, 'open' => false]);
    }

    public function find(?string $id): ?array
    {
        foreach ($this->all() as $q) {
            if (($q['id'] ?? null) === $id) {
                return $q;
            }
        }

        return null;
    }

    public function findOpen(?string $id): ?array
    {
        $q = $this->find($id);

        return $q !== null && $q['open'] ? $q : null;
    }

    /** Absolute URL of a generated poster, or null when there is none. */
    public function generatedPoster(string $id): ?string
    {
        if (! PosterGenerator::safeId($id) || ! is_file(public_path(PosterGenerator::DIR.'/'.$id.'.'.PosterGenerator::EXT))) {
            return null;
        }

        return rtrim((string) config('tbwim.url'), '/').'/'.PosterGenerator::DIR.'/'.$id.'.'.PosterGenerator::EXT;
    }

    private function parse(mixed $value): ?Carbon
    {
        if (empty($value)) {
            return null;
        }
        try {
            return Carbon::parse((string) $value, LaunchSchedule::TZ);
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return list<array<string, mixed>> */
    private function fallback(string $base): array
    {
        return array_values(array_map(function (array $q) use ($base): array {
            foreach (['video', 'poster'] as $key) {
                if (! empty($q[$key]) && str_starts_with((string) $q[$key], '/')) {
                    $q[$key] = $base.$q[$key];
                }
            }
            if (is_string($q['id'] ?? null) && ($local = $this->generatedPoster($q['id'])) !== null) {
                $q['poster'] = $local;
            }
            $at = $this->parse($q['opens_at'] ?? null);
            $q['open'] = $at !== null && $at->isPast();

            return $q;
        }, (array) config('tbwim.questions', [])));
    }
}
