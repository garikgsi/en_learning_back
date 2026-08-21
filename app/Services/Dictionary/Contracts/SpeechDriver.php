<?php

namespace App\Services\Dictionary\Contracts;

use App\Services\Dictionary\Data\SpeechRequest;
use App\Services\Dictionary\Data\SpeechResult;

interface SpeechDriver
{
    public function name(): string;

    public function cacheVersion(): string;

    public function audio(SpeechRequest $request): ?SpeechResult;
}
