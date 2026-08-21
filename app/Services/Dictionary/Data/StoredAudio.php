<?php

namespace App\Services\Dictionary\Data;

final readonly class StoredAudio
{
    public function __construct(
        public string $path,
        public string $contentType,
    ) {}
}
