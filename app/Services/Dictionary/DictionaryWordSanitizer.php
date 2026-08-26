<?php

namespace App\Services\Dictionary;

use InvalidArgumentException;

class DictionaryWordSanitizer
{
    /**
     * Commas in these legacy phrases are intentional punctuation, not variant separators.
     *
     * @var list<string>
     */
    private const COMMA_EXCEPTIONS = [
        "I'm fine, thanks",
        'PSHE (Personal, Social and Health Education)',
        'Спасибо, хорошо',
        'Вот, пожалуйста',
    ];

    /** @var array<string, string> */
    private const SEMANTIC_REPLACEMENTS = [
        'I’m fine, thanks.' => "I'm fine, thanks",
        'PSHE (Personal, Social & Health Education)' => 'PSHE (Personal, Social and Health Education)',
        'Спасибо, хорошо.' => 'Спасибо, хорошо',
        'Вот, пожалуйста.' => 'Вот, пожалуйста',
        'оно (о предметах, животных)' => 'оно (о предметах или животных)',
        'Откуда ты (из какой страны, города)?' => 'Откуда ты (из какой страны или города)?',
        'думать о ком-либо, чём-либо' => 'думать о ком-либо или чём-либо',
        'гордиться чем-либо, кем-либо' => 'гордиться чем-либо или кем-либо',
        'Какие они? (по нраву, характеру)' => 'Какие они? (по нраву или характеру)',
        'прикрепить скотчем (зд.)' => 'прикрепить скотчем (зд)',
        'такой ..., как' => 'такой как',
        'as ... as' => 'as as',
        'быть в возрасте 25 лет' => 'быть в возрасте двадцати пяти лет',
    ];

    /**
     * @param  array{ru: string, en: string, ru_variants?: list<string>|null, en_variants?: list<string>|null}  $word
     * @return array{ru: string, en: string, ru_variants: list<string>, en_variants: list<string>}
     */
    public function sanitize(array $word): array
    {
        [$ru, $ruVariants] = $this->sanitizeField(
            $word['ru'],
            $word['ru_variants'] ?? [],
        );
        [$en, $enVariants] = $this->sanitizeField(
            $word['en'],
            $word['en_variants'] ?? [],
        );

        return [
            'ru' => $ru,
            'en' => $en,
            'ru_variants' => $ruVariants,
            'en_variants' => $enVariants,
        ];
    }

    /**
     * @param  list<string>  $existingVariants
     * @return array{string, list<string>}
     */
    private function sanitizeField(string $primary, array $existingVariants): array
    {
        $primaryParts = $this->sanitizeValue($primary);
        $cleanPrimary = array_shift($primaryParts);

        if ($cleanPrimary === null || $cleanPrimary === '') {
            throw new InvalidArgumentException('Dictionary word cannot be empty after sanitizing.');
        }

        $variants = $primaryParts;

        foreach ($existingVariants as $variant) {
            array_push($variants, ...$this->sanitizeValue($variant));
        }

        return [$cleanPrimary, $this->uniqueVariants($variants, $cleanPrimary)];
    }

    /** @return list<string> */
    private function sanitizeValue(string $value): array
    {
        $value = self::SEMANTIC_REPLACEMENTS[$value] ?? $value;
        $value = str_replace(['’', '&', '«', '»'], ["'", 'and', '', ''], $value);

        $parts = in_array($value, self::COMMA_EXCEPTIONS, true)
            ? [$value]
            : preg_split('/\s*[,\/;]\s*/u', $value, -1, PREG_SPLIT_NO_EMPTY);

        if ($parts === false) {
            throw new InvalidArgumentException('Unable to split dictionary word.');
        }

        return array_values(array_filter(array_map(function (string $part): string {
            $part = str_replace('.', '', $part);
            $part = preg_replace('/\s+/u', ' ', trim($part)) ?? '';

            if ($part !== '' && preg_match("~^[\\p{L}() ,'!?-]+$~u", $part) !== 1) {
                throw new InvalidArgumentException("Unsupported dictionary characters: {$part}");
            }

            return $part;
        }, $parts)));
    }

    /**
     * @param  list<string>  $variants
     * @return list<string>
     */
    private function uniqueVariants(array $variants, string $primary): array
    {
        $seen = [mb_strtolower($primary) => true];
        $unique = [];

        foreach ($variants as $variant) {
            $key = mb_strtolower($variant);

            if ($variant === '' || isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $unique[] = $variant;
        }

        return $unique;
    }
}
