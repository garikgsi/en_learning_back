<?php

namespace App\Services\Dictionary;

use App\Services\Dictionary\Contracts\SpeechDriver;
use App\Services\Dictionary\Data\SpeechRequest;
use App\Services\Dictionary\Data\SpeechResult;
use App\Services\Dictionary\Storage\DictionaryAudioStorage;
use Throwable;

class DictionarySpeechService
{
    public function __construct(
        private readonly SpeechDriver $speechDriver,
        private readonly DictionaryAudioStorage $audioStorage,
    ) {}

    public function audio(SpeechRequest $request): ?SpeechResult
    {
        try {
            $storedPath = $this->audioStorage->find(
                $request,
                $this->speechDriver,
            );

            if ($storedPath !== null) {
                return new SpeechResult(
                    $this->audioStorage->get($storedPath),
                    $request->format->contentType(),
                );
            }
        } catch (Throwable $exception) {
            report($exception);
        }

        $audio = $this->speechDriver->audio($request);

        if ($audio === null) {
            return null;
        }

        try {
            $this->audioStorage->put(
                $request,
                $this->speechDriver,
                $audio,
            );
        } catch (Throwable $exception) {
            report($exception);
        }

        return $audio;
    }
}
