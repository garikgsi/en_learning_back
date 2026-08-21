<?php

namespace App\Services\Dictionary\Drivers\Translation;

use App\Services\Dictionary\Contracts\TranslationDriver;
use App\Services\Dictionary\Data\TranslationRequest;
use App\Services\Dictionary\Data\TranslationResult;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class MyMemoryTranslationDriver implements TranslationDriver
{
    public function translate(TranslationRequest $request): TranslationResult
    {
        $normalizedText = trim($request->text);
        $cacheKey = implode(':', [
            'dictionary-translation',
            $request->sourceLanguage,
            $request->targetLanguage,
            hash('sha256', mb_strtolower($normalizedText)),
        ]);

        return Cache::remember(
            $cacheKey,
            now()->addDays(30),
            function () use ($normalizedText, $request): TranslationResult {
                $query = [
                    'q' => $normalizedText,
                    'langpair' => "{$request->sourceLanguage}|{$request->targetLanguage}",
                ];
                $email = config('services.dictionary_translation.email');

                if (is_string($email) && $email !== '') {
                    $query['de'] = $email;
                }

                $response = $this->request()->get(
                    (string) config('services.dictionary_translation.url'),
                    $query,
                );
                $response->throw();
                $translation = $response->json(
                    'responseData.translatedText',
                );

                if (! is_string($translation) || trim($translation) === '') {
                    throw new RuntimeException(
                        'Сервис перевода не вернул результат.',
                    );
                }

                return new TranslationResult(trim(html_entity_decode(
                    $translation,
                    ENT_QUOTES | ENT_HTML5,
                    'UTF-8',
                )));
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
