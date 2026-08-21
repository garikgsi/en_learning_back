<?php

namespace App\Services\Dictionary\Data;

final readonly class PhoneticsResult
{
    public function __construct(
        public ?string $transcription,
        public ?string $sourceAudioUrl = null,
    ) {}
}
