<?php

namespace App\Services\Dictionary\Data;

final readonly class TranslationRequest
{
    public function __construct(
        public string $text,
        public string $sourceLanguage,
        public string $targetLanguage,
    ) {}
}
