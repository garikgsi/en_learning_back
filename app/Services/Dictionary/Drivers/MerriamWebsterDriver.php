<?php

namespace App\Services\Dictionary\Drivers;

use App\Services\Dictionary\Clients\MerriamWebsterClient;
use App\Services\Dictionary\Contracts\PhoneticsDriver;
use App\Services\Dictionary\Contracts\SpeechDriver;
use App\Services\Dictionary\Data\PhoneticsResult;
use App\Services\Dictionary\Data\SpeechRequest;
use App\Services\Dictionary\Data\SpeechResult;
use App\Services\Dictionary\Enums\AudioFormat;
use Illuminate\Support\Facades\Http;

class MerriamWebsterDriver implements PhoneticsDriver, SpeechDriver
{
    public function __construct(
        private readonly MerriamWebsterClient $client,
    ) {}

    public function find(string $english): ?PhoneticsResult
    {
        $pronunciation = $this->client->pronunciation($english);

        if ($pronunciation === null) {
            return null;
        }

        return new PhoneticsResult(
            $pronunciation['transcription'],
            $pronunciation['audioUrl'],
        );
    }

    public function name(): string
    {
        return 'merriam_webster';
    }

    public function cacheVersion(): string
    {
        return 'v1';
    }

    public function audio(SpeechRequest $request): ?SpeechResult
    {
        if ($request->format !== AudioFormat::Mp3) {
            return null;
        }

        $audioUrl = $this->client
            ->pronunciation($request->text)['audioUrl'] ?? null;

        if ($audioUrl === null) {
            return null;
        }

        $response = Http::accept('audio/mpeg')
            ->timeout(20)
            ->retry(2, 250)
            ->get($audioUrl);
        $response->throw();

        return new SpeechResult($response->body(), 'audio/mpeg');
    }
}
