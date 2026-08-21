<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class WordVariantSynchronizer
{
    public function synchronize(): void
    {
        $words = DB::table('words')
            ->select(['id', 'ru', 'en'])
            ->orderBy('id')
            ->get()
            ->all();
        $indexes = [
            'ru' => $this->buildIndex($words, 'ru'),
            'en' => $this->buildIndex($words, 'en'),
        ];

        foreach ($words as $word) {
            DB::table('words')
                ->where('id', $word->id)
                ->update([
                    'ru_variants' => json_encode(
                        $this->variantsFor($word, $words, $indexes['en'], 'en', 'ru'),
                        JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE,
                    ),
                    'en_variants' => json_encode(
                        $this->variantsFor($word, $words, $indexes['ru'], 'ru', 'en'),
                        JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE,
                    ),
                ]);
        }
    }

    /**
     * @param  array<int, object{id: int, ru: string, en: string}>  $words
     * @return array<string, list<int>>
     */
    private function buildIndex(array $words, string $field): array
    {
        $index = [];

        foreach ($words as $position => $word) {
            foreach ($this->split($word->{$field}) as $value) {
                $index[$this->normalize($value)][] = $position;
            }
        }

        return $index;
    }

    /**
     * @param  object{id: int, ru: string, en: string}  $word
     * @param  array<int, object{id: int, ru: string, en: string}>  $words
     * @param  array<string, list<int>>  $sourceIndex
     * @return list<string>
     */
    private function variantsFor(
        object $word,
        array $words,
        array $sourceIndex,
        string $sourceField,
        string $targetField,
    ): array {
        $currentValues = array_fill_keys(
            array_map(
                $this->normalize(...),
                $this->split($word->{$targetField}),
            ),
            true,
        );
        $variants = [];

        foreach ($this->split($word->{$sourceField}) as $sourceValue) {
            foreach ($sourceIndex[$this->normalize($sourceValue)] ?? [] as $position) {
                foreach ($this->split($words[$position]->{$targetField}) as $value) {
                    $normalized = $this->normalize($value);

                    if (isset($currentValues[$normalized])
                        || isset($variants[$normalized])) {
                        continue;
                    }

                    $variants[$normalized] = $value;
                }
            }
        }

        return array_values($variants);
    }

    /**
     * @return list<string>
     */
    private function split(string $value): array
    {
        return array_values(array_filter(
            array_map(trim(...), explode(',', $value)),
            fn (string $variant): bool => $variant !== '',
        ));
    }

    private function normalize(string $value): string
    {
        return mb_strtolower(trim($value));
    }
}
