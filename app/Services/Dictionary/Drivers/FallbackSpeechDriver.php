<?php

namespace App\Services\Dictionary\Drivers;

use App\Services\Dictionary\Contracts\SpeechDriver;
use App\Services\Dictionary\Data\SpeechRequest;
use App\Services\Dictionary\Data\SpeechResult;
use Throwable;

class FallbackSpeechDriver implements SpeechDriver
{
    /**
     * @param  list<SpeechDriver>  $drivers
     */
    public function __construct(private readonly array $drivers) {}

    public function name(): string
    {
        return 'fallback_'.implode('_', array_map(
            fn (SpeechDriver $driver): string => $driver->name(),
            $this->drivers,
        ));
    }

    public function cacheVersion(): string
    {
        return hash('sha256', implode('|', array_map(
            fn (SpeechDriver $driver): string => implode(':', [
                $driver->name(),
                $driver->cacheVersion(),
            ]),
            $this->drivers,
        )));
    }

    public function audio(SpeechRequest $request): ?SpeechResult
    {
        foreach ($this->drivers as $driver) {
            try {
                $result = $driver->audio($request);

                if ($result !== null) {
                    return $result;
                }
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return null;
    }
}
