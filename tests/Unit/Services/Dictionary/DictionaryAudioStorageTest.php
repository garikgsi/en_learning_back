<?php

namespace Tests\Unit\Services\Dictionary;

use App\Services\Dictionary\Contracts\SpeechDriver;
use App\Services\Dictionary\Data\SpeechRequest;
use App\Services\Dictionary\Data\SpeechResult;
use App\Services\Dictionary\DictionarySpeechService;
use App\Services\Dictionary\Storage\DictionaryAudioStorage;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DictionaryAudioStorageTest extends TestCase
{
    public function test_it_stores_audio_on_the_configured_private_disk(): void
    {
        Storage::fake('dictionary_audio');
        $driver = $this->driver('provider-v1');
        $request = new SpeechRequest('Store', 'en-US', 'Jenny');
        $storage = app(DictionaryAudioStorage::class);

        $path = $storage->put(
            $request,
            $driver,
            new SpeechResult('mp3 contents', 'audio/mpeg'),
        );

        Storage::disk('dictionary_audio')->assertExists($path);
        $this->assertTrue($storage->exists($request, $driver));
        $this->assertSame($path, $storage->find($request, $driver));
    }

    public function test_cache_path_depends_on_driver_version_and_not_case(): void
    {
        $storage = app(DictionaryAudioStorage::class);
        $firstRequest = new SpeechRequest(' Store ', 'en-US', 'Jenny');
        $sameRequest = new SpeechRequest('store', 'EN-us', 'jenny');

        $this->assertSame(
            $storage->path($firstRequest, $this->driver('provider-v1')),
            $storage->path($sameRequest, $this->driver('provider-v1')),
        );
        $this->assertNotSame(
            $storage->path($firstRequest, $this->driver('provider-v1')),
            $storage->path($firstRequest, $this->driver('provider-v2')),
        );
    }

    public function test_speech_service_reuses_the_locally_stored_audio(): void
    {
        Storage::fake('dictionary_audio');
        $driver = new class implements SpeechDriver
        {
            public int $requests = 0;

            public function name(): string
            {
                return 'test';
            }

            public function cacheVersion(): string
            {
                return 'v1';
            }

            public function audio(SpeechRequest $request): ?SpeechResult
            {
                $this->requests++;

                return new SpeechResult('generated audio', 'audio/mpeg');
            }
        };
        $service = new DictionarySpeechService(
            $driver,
            app(DictionaryAudioStorage::class),
        );
        $request = new SpeechRequest('store');

        $first = $service->audio($request);
        $second = $service->audio($request);

        $this->assertSame('generated audio', $first->contents);
        $this->assertSame($first->contents, $second->contents);
        $this->assertCount(
            1,
            Storage::disk('dictionary_audio')->allFiles(),
        );
        $this->assertSame(1, $driver->requests);
    }

    public function test_speech_service_returns_audio_when_cache_is_unavailable(): void
    {
        $driver = $this->driver('provider-v1');
        $storage = new class extends DictionaryAudioStorage
        {
            public function find(
                SpeechRequest $request,
                SpeechDriver $driver,
            ): ?string {
                throw new \RuntimeException('Audio cache cannot be read.');
            }

            public function put(
                SpeechRequest $request,
                SpeechDriver $driver,
                SpeechResult $result,
            ): string {
                throw new \RuntimeException('Audio cache cannot be written.');
            }
        };
        $service = new DictionarySpeechService($driver, $storage);

        $audio = $service->audio(new SpeechRequest('store'));

        $this->assertNotNull($audio);
        $this->assertSame('audio', $audio->contents);
        $this->assertSame('audio/mpeg', $audio->contentType);
    }

    private function driver(string $version): SpeechDriver
    {
        return new class($version) implements SpeechDriver
        {
            public function __construct(private readonly string $version) {}

            public function name(): string
            {
                return 'test';
            }

            public function cacheVersion(): string
            {
                return $this->version;
            }

            public function audio(SpeechRequest $request): ?SpeechResult
            {
                return new SpeechResult('audio', 'audio/mpeg');
            }
        };
    }
}
