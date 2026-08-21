<?php

namespace App\Services\Dictionary\Data;

use App\Services\Dictionary\Enums\AudioFormat;

final readonly class SpeechRequest
{
    public function __construct(
        public string $text,
        public string $locale = 'en-US',
        public ?string $voice = null,
        public AudioFormat $format = AudioFormat::Mp3,
    ) {}
}
