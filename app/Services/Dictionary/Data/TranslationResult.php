<?php

namespace App\Services\Dictionary\Data;

final readonly class TranslationResult
{
    /**
     * @param  list<string>  $alternatives
     */
    public function __construct(
        public string $translation,
        public array $alternatives = [],
    ) {}
}
