<?php

namespace App\Services\Dictionary\Data;

final readonly class SpeechResult
{
    public function __construct(
        public string $contents,
        public string $contentType,
    ) {}
}
