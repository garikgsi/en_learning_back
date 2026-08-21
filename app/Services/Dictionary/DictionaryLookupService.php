<?php

namespace App\Services\Dictionary;

use App\Services\Dictionary\Contracts\PhoneticsDriver;
use App\Services\Dictionary\Contracts\TranslationDriver;
use App\Services\Dictionary\Data\TranslationRequest;

class DictionaryLookupService
{
    public function __construct(
        private readonly TranslationDriver $translationDriver,
        private readonly PhoneticsDriver $phoneticsDriver,
    ) {}

    /**
     * @return array{
     *     russian: string,
     *     english: string,
     *     transcription: string|null
     * }
     */
    public function lookup(string $word, string $sourceLanguage): array
    {
        $sourceWord = trim($word);

        if ($sourceLanguage === 'ru') {
            $russian = $sourceWord;
            $english = $this->translationDriver->translate(
                new TranslationRequest($sourceWord, 'ru', 'en'),
            )->translation;
        } else {
            $english = $sourceWord;
            $russian = $this->translationDriver->translate(
                new TranslationRequest($sourceWord, 'en', 'ru'),
            )->translation;
        }

        $phonetics = $this->phoneticsDriver->find($english);

        return [
            'russian' => $russian,
            'english' => $english,
            'transcription' => $phonetics?->transcription,
        ];
    }
}
