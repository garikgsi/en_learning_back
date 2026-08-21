<?php

namespace App\Services\Dictionary\Contracts;

use App\Services\Dictionary\Data\TranslationRequest;
use App\Services\Dictionary\Data\TranslationResult;

interface TranslationDriver
{
    public function translate(TranslationRequest $request): TranslationResult;
}
