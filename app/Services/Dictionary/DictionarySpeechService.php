<?php

namespace App\Services\Dictionary;

use App\Services\Dictionary\Contracts\SpeechDriver;
use App\Services\Dictionary\Data\SpeechRequest;
use App\Services\Dictionary\Data\StoredAudio;
use App\Services\Dictionary\Storage\DictionaryAudioStorage;

class DictionarySpeechService
{
    public function __construct(
        private readonly SpeechDriver $speechDriver,
        private readonly DictionaryAudioStorage $audioStorage,
    ) {}

    public function audio(SpeechRequest $request): ?StoredAudio
    {
        $storedPath = $this->audioStorage->find(
            $request,
            $this->speechDriver,
        );

        if ($storedPath !== null) {
            return new StoredAudio(
                $storedPath,
                $request->format->contentType(),
            );
        }

        $audio = $this->speechDriver->audio($request);

        if ($audio === null) {
            return null;
        }

        $storedPath = $this->audioStorage->put(
            $request,
            $this->speechDriver,
            $audio,
        );

        return new StoredAudio($storedPath, $audio->contentType);
    }
}
