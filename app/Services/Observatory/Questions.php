<?php

declare(strict_types=1);

namespace App\Services\Observatory;

use Illuminate\Support\Carbon;

/** The question catalogue from config: which are open, which is current, where its media lives. */
final class Questions
{
    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        $base = (string) config('observatory.media_base');

        return array_values(array_map(static function (array $q) use ($base): array {
            foreach (['video', 'poster'] as $key) {
                if (! empty($q[$key]) && str_starts_with((string) $q[$key], '/')) {
                    $q[$key] = $base.$q[$key];
                }
            }
            $q['open'] = self::isOpen($q);

            return $q;
        }, (array) config('observatory.questions', [])));
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
            $at = Carbon::parse((string) $q['opens_at'], (string) config('observatory.timezone'));
            if ($currentAt === null || $at->gt($currentAt)) {
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

    private static function isOpen(array $q): bool
    {
        if (empty($q['opens_at'])) {
            return false;
        }
        try {
            return Carbon::parse((string) $q['opens_at'], (string) config('observatory.timezone'))->isPast();
        } catch (\Throwable) {
            return false;
        }
    }
}
