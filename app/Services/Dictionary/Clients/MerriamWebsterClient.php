<?php

namespace App\Services\Dictionary\Clients;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class MerriamWebsterClient
{
    /**
     * @return array{transcription: string|null, audioUrl: string|null}|null
     */
    public function pronunciation(string $english): ?array
    {
        $normalizedWord = mb_strtolower(trim($english));

        if ($normalizedWord === '') {
            return null;
        }

        $entries = $this->entries($normalizedWord);
        $entry = collect($entries)
            ->filter(fn ($item): bool => is_array($item))
            ->first(function (array $item) use ($normalizedWord): bool {
                $id = data_get($item, 'meta.id');

                return is_string($id)
                    && mb_strtolower(explode(':', $id, 2)[0])
                        === $normalizedWord;
            }) ?? collect($entries)->first(fn ($item): bool => is_array($item));

        if (! is_array($entry)) {
            return null;
        }

        $pronunciations = data_get($entry, 'hwi.prs', []);

        if (! is_array($pronunciations)) {
            return null;
        }

        $transcription = null;
        $audioId = null;

        foreach ($pronunciations as $pronunciation) {
            if (! is_array($pronunciation)) {
                continue;
            }

            $ipa = $pronunciation['ipa'] ?? null;
            $audio = data_get($pronunciation, 'sound.audio');

            if ($transcription === null
                && is_string($ipa)
                && trim($ipa) !== '') {
                $transcription = '/'.trim($ipa, " /\t\n\r\0\x0B").'/';
            }

            if ($audioId === null
                && is_string($audio)
                && trim($audio) !== '') {
                $audioId = trim($audio);
            }
        }

        if ($transcription === null && $audioId === null) {
            return null;
        }

        return [
            'transcription' => $transcription,
            'audioUrl' => $audioId === null
                ? null
                : $this->audioUrl($audioId),
        ];
    }

    /**
     * @return list<mixed>
     */
    private function entries(string $english): array
    {
        $key = config('services.merriam_webster.key');

        if (! is_string($key) || $key === '') {
            throw new RuntimeException('Не указан ключ Merriam-Webster.');
        }

        return Cache::remember(
            'merriam-webster:'.hash('sha256', $english),
            now()->addDays(30),
            function () use ($english, $key): array {
                $url = implode('/', [
                    rtrim((string) config('services.merriam_webster.url'), '/'),
                    trim((string) config(
                        'services.merriam_webster.reference',
                    ), '/'),
                    'json',
                    rawurlencode($english),
                ]);
                $response = $this->request()->get($url, ['key' => $key]);
                $response->throw();
                $entries = $response->json();

                return is_array($entries) ? array_values($entries) : [];
            },
        );
    }

    private function audioUrl(string $audioId): string
    {
        $lowerAudioId = mb_strtolower($audioId);
        $directory = match (true) {
            str_starts_with($lowerAudioId, 'bix') => 'bix',
            str_starts_with($lowerAudioId, 'gg') => 'gg',
            preg_match('/^[^a-z]/', $lowerAudioId) === 1 => 'number',
            default => mb_substr($lowerAudioId, 0, 1),
        };

        return implode('/', [
            rtrim((string) config(
                'services.merriam_webster.audio_url',
            ), '/'),
            $directory,
            rawurlencode($audioId).'.mp3',
        ]);
    }

    private function request(): PendingRequest
    {
        return Http::acceptJson()
            ->timeout(15)
            ->retry(2, 250);
    }
}
