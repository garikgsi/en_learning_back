<?php

namespace App\Providers;

use App\Services\Dictionary\Contracts\PhoneticsDriver;
use App\Services\Dictionary\Contracts\SpeechDriver;
use App\Services\Dictionary\Contracts\TranslationDriver;
use App\Services\Dictionary\Drivers\FallbackPhoneticsDriver;
use App\Services\Dictionary\Drivers\FallbackSpeechDriver;
use App\Services\Dictionary\Drivers\MerriamWebsterDriver;
use App\Services\Dictionary\Drivers\Phonetics\FreeDictionaryPhoneticsDriver;
use App\Services\Dictionary\Drivers\Speech\VoiceRssSpeechDriver;
use App\Services\Dictionary\Drivers\Translation\MyMemoryTranslationDriver;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(MerriamWebsterDriver::class);

        $this->app->bind(
            TranslationDriver::class,
            fn (Application $app): TranslationDriver => match (config(
                'dictionary.drivers.translation',
            )) {
                'my_memory' => $app->make(MyMemoryTranslationDriver::class),
                default => throw new InvalidArgumentException(
                    'Неизвестный драйвер перевода.',
                ),
            },
        );
        $this->app->bind(
            PhoneticsDriver::class,
            fn (Application $app): PhoneticsDriver => match (config(
                'dictionary.drivers.phonetics',
            )) {
                'merriam_webster_fallback' => new FallbackPhoneticsDriver([
                    $app->make(MerriamWebsterDriver::class),
                    $app->make(FreeDictionaryPhoneticsDriver::class),
                ]),
                'free_dictionary' => $app->make(
                    FreeDictionaryPhoneticsDriver::class,
                ),
                default => throw new InvalidArgumentException(
                    'Неизвестный драйвер фонетики.',
                ),
            },
        );
        $this->app->bind(
            SpeechDriver::class,
            fn (Application $app): SpeechDriver => match (config(
                'dictionary.drivers.speech',
            )) {
                'merriam_webster_voice_rss' => new FallbackSpeechDriver([
                    $app->make(MerriamWebsterDriver::class),
                    $app->make(VoiceRssSpeechDriver::class),
                ]),
                'voice_rss' => $app->make(VoiceRssSpeechDriver::class),
                default => throw new InvalidArgumentException(
                    'Неизвестный драйвер озвучки.',
                ),
            },
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
