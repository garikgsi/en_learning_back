<?php

namespace App\Services\Dictionary\Drivers\Phonetics;

use App\Services\Dictionary\Contracts\PhoneticsDriver;
use App\Services\Dictionary\Data\PhoneticsResult;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class FreeDictionaryPhoneticsDriver implements PhoneticsDriver
{
    public function find(string $english): ?PhoneticsResult
    {
        $normalizedWord = mb_strtolower(trim($english));

        if ($normalizedWord === '' || str_contains($normalizedWord, ' ')) {
            return null;
        }

        return Cache::remember(
            'dictionary-phonetics:'.hash('sha256', $normalizedWord),
            now()->addDays(30),
            function () use ($normalizedWord): ?PhoneticsResult {
                $url = rtrim((string) config(
                    'services.dictionary_pronunciation.url',
                ), '/').'/'.rawurlencode($normalizedWord);
                $response = $this->request()->get($url);

                if ($response->notFound()) {
                    return null;
                }

                $response->throw();
                $entries = $response->json();

                if (! is_array($entries)) {
                    return null;
                }

                $transcription = null;
                $audioUrl = null;

                foreach ($entries as $entry) {
                    if (! is_array($entry)) {
                        continue;
                    }

                    $phonetic = $entry['phonetic'] ?? null;

                    if ($transcription === null
                        && is_string($phonetic)
                        && trim($phonetic) !== '') {
                        $transcription = trim($phonetic);
                    }

                    foreach ($entry['phonetics'] ?? [] as $item) {
                        if (! is_array($item)) {
                            continue;
                        }

                        $text = $item['text'] ?? null;
                        $audio = $item['audio'] ?? null;

                        if ($transcription === null
                            && is_string($text)
                            && trim($text) !== '') {
                            $transcription = trim($text);
                        }

                        if ($audioUrl === null
                            && is_string($audio)
                            && trim($audio) !== '') {
                            $audioUrl = trim($audio);
                        }
                    }
                }

                if ($transcription === null && $audioUrl === null) {
                    return null;
                }

                return new PhoneticsResult($transcription, $audioUrl);
            },
        );
    }

    private function request(): PendingRequest
    {
        return Http::acceptJson()
            ->timeout(10)
            ->retry(2, 200);
    }
}
