<?php

namespace Tests\Unit\Services\Dictionary;

use App\Services\Dictionary\DictionaryWordSanitizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DictionaryWordSanitizerTest extends TestCase
{
    #[DataProvider('sanitizingCases')]
    public function test_it_sanitizes_dictionary_words(array $word, array $expected): void
    {
        $this->assertSame($expected, (new DictionaryWordSanitizer)->sanitize($word));
    }

    public static function sanitizingCases(): array
    {
        return [
            'separators become variants' => [
                ['ru' => 'американец / американский; житель США', 'en' => 'American'],
                [
                    'ru' => 'американец',
                    'en' => 'American',
                    'ru_variants' => ['американский', 'житель США'],
                    'en_variants' => [],
                ],
            ],
            'legacy comma exceptions' => [
                ['ru' => 'Спасибо, хорошо.', 'en' => 'I’m fine, thanks.'],
                [
                    'ru' => 'Спасибо, хорошо',
                    'en' => "I'm fine, thanks",
                    'ru_variants' => [],
                    'en_variants' => [],
                ],
            ],
            'semantic replacements' => [
                ['ru' => 'такой ..., как', 'en' => 'as ... as'],
                [
                    'ru' => 'такой как',
                    'en' => 'as as',
                    'ru_variants' => [],
                    'en_variants' => [],
                ],
            ],
            'quotes and punctuation' => [
                ['ru' => '«визитная карточка»', 'en' => 'calling card.'],
                [
                    'ru' => 'визитная карточка',
                    'en' => 'calling card',
                    'ru_variants' => [],
                    'en_variants' => [],
                ],
            ],
        ];
    }
}
