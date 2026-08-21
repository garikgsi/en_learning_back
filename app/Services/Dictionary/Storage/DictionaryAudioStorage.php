<?php

namespace App\Services\Dictionary\Storage;

use App\Services\Dictionary\Contracts\SpeechDriver;
use App\Services\Dictionary\Data\SpeechRequest;
use App\Services\Dictionary\Data\SpeechResult;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class DictionaryAudioStorage
{
    public function exists(
        SpeechRequest $request,
        SpeechDriver $driver,
    ): bool {
        return $this->disk()->exists($this->path($request, $driver));
    }

    public function find(
        SpeechRequest $request,
        SpeechDriver $driver,
    ): ?string {
        $path = $this->path($request, $driver);

        return $this->disk()->exists($path) ? $path : null;
    }

    public function put(
        SpeechRequest $request,
        SpeechDriver $driver,
        SpeechResult $result,
    ): string {
        if ($result->contentType !== $request->format->contentType()) {
            throw new RuntimeException(
                'Формат аудио не соответствует запрошенному формату.',
            );
        }

        $path = $this->path($request, $driver);

        if (! $this->disk()->put($path, $result->contents)) {
            throw new RuntimeException('Не удалось сохранить аудиофайл.');
        }

        return $path;
    }

    public function path(
        SpeechRequest $request,
        SpeechDriver $driver,
    ): string {
        $payload = implode("\0", [
            mb_strtolower(trim($request->text)),
            mb_strtolower(trim($request->locale)),
            mb_strtolower(trim($request->voice ?? 'default')),
            $request->format->value,
            $driver->name(),
            $driver->cacheVersion(),
        ]);
        $hash = hash('sha256', $payload);

        return implode('/', [
            substr($hash, 0, 2),
            substr($hash, 2, 2),
            $hash.'.'.$request->format->value,
        ]);
    }

    private function disk(): FilesystemAdapter
    {
        return Storage::disk((string) config(
            'dictionary.audio.disk',
            'dictionary_audio',
        ));
    }
}
