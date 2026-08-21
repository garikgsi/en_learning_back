<?php

namespace App\Services;

use App\Models\Word;

class ExerciseWordDeduplicator
{
    /**
     * @param  array<int, Word>  $words
     * @return array<int, Word>
     */
    public function unique(array $words, int $limit = PHP_INT_MAX): array
    {
        $selected = [];
        $seen = ['ru' => [], 'en' => []];

        foreach ($words as $word) {
            $keys = [
                'ru' => $this->keys($word->ru),
                'en' => $this->keys($word->en),
            ];

            if ($this->intersects($seen['ru'], $keys['ru'])
                || $this->intersects($seen['en'], $keys['en'])) {
                continue;
            }

            $selected[] = $word;
            $seen['ru'] = [...$seen['ru'], ...$keys['ru']];
            $seen['en'] = [...$seen['en'], ...$keys['en']];

            if (count($selected) >= $limit) {
                break;
            }
        }

        return $selected;
    }

    /**
     * @return list<string>
     */
    private function keys(string $value): array
    {
        return array_values(array_unique(array_map(
            fn (string $variant): string => mb_strtolower(trim($variant)),
            array_filter(
                explode(',', $value),
                fn (string $variant): bool => trim($variant) !== '',
            ),
        )));
    }

    /**
     * @param  list<string>  $first
     * @param  list<string>  $second
     */
    private function intersects(array $first, array $second): bool
    {
        return array_intersect($first, $second) !== [];
    }
}
