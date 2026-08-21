<?php

namespace App\Services\Dictionary\Drivers\Speech;

use App\Services\Dictionary\Contracts\SpeechDriver;
use App\Services\Dictionary\Data\SpeechRequest;
use App\Services\Dictionary\Data\SpeechResult;
use App\Services\Dictionary\Enums\AudioFormat;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class VoiceRssSpeechDriver implements SpeechDriver
{
    public function name(): string
    {
        return 'voice_rss';
    }

    public function cacheVersion(): string
    {
        return 'v1-'.hash('sha256', implode('|', [
            (string) config('services.voice_rss.voice'),
            (string) config('services.voice_rss.audio_format'),
        ]));
    }

    public function audio(SpeechRequest $request): ?SpeechResult
    {
        if ($request->format !== AudioFormat::Mp3) {
            return null;
        }

        $key = config('services.voice_rss.key');

        if (! is_string($key) || $key === '') {
            throw new RuntimeException('Не указан ключ VoiceRSS.');
        }

        $response = Http::asForm()
            ->accept('audio/mpeg')
            ->timeout(30)
            ->retry(2, 250)
            ->post((string) config('services.voice_rss.url'), [
                'key' => $key,
                'src' => trim($request->text),
                'hl' => mb_strtolower($request->locale),
                'v' => $request->voice
                    ?? config('services.voice_rss.voice'),
                'c' => 'MP3',
                'f' => config('services.voice_rss.audio_format'),
                'b64' => 'false',
            ]);
        $response->throw();
        $contents = $response->body();

        if ($contents === '' || str_starts_with($contents, 'ERROR')) {
            throw new RuntimeException(
                $contents !== ''
                    ? $contents
                    : 'VoiceRSS не вернул аудиофайл.',
            );
        }

        return new SpeechResult($contents, 'audio/mpeg');
    }
}
