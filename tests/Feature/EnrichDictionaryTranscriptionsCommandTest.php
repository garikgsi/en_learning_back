<?php

namespace Tests\Feature;

use App\Models\Word;
use App\Services\Dictionary\Contracts\PhoneticsDriver;
use App\Services\Dictionary\Data\PhoneticsResult;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class EnrichDictionaryTranscriptionsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_fills_a_missing_transcription(): void
    {
        $word = Word::query()->create([
            'ru' => 'дом',
            'en' => 'home',
            'grade' => 1,
        ]);
        $this->mock(
            PhoneticsDriver::class,
            function (MockInterface $mock): void {
                $mock->shouldReceive('find')
                    ->once()
                    ->with('home')
                    ->andReturn(new PhoneticsResult('/həʊm/', null));
            },
        );

        $this->artisan('dictionary:enrich-transcriptions')
            ->assertSuccessful();

        $word->refresh();
        $this->assertSame('/həʊm/', $word->transcription);
        $this->assertNotNull($word->transcription_checked_at);
    }

    public function test_it_does_not_recheck_an_unavailable_transcription_for_a_day(): void
    {
        $word = Word::query()->create([
            'ru' => 'несуществующее слово',
            'en' => 'missingword',
            'grade' => 1,
        ]);
        $this->mock(
            PhoneticsDriver::class,
            function (MockInterface $mock): void {
                $mock->shouldReceive('find')
                    ->once()
                    ->with('missingword')
                    ->andReturnNull();
            },
        );

        $this->artisan('dictionary:enrich-transcriptions')
            ->assertSuccessful();
        $this->artisan('dictionary:enrich-transcriptions')
            ->assertSuccessful();

        $word->refresh();
        $this->assertNull($word->transcription);
        $this->assertNotNull($word->transcription_checked_at);
    }

    public function test_it_is_scheduled_every_ten_minutes(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(
                fn ($event): bool => str_contains(
                    $event->command,
                    'dictionary:enrich-transcriptions',
                ),
            );

        $this->assertNotNull($event);

        foreach (['00:00', '00:10', '00:20'] as $time) {
            $this->travelTo(CarbonImmutable::parse("2026-08-21 {$time}:00"));
            $this->assertTrue($event->isDue(app()));
        }

        $this->travelTo(CarbonImmutable::parse('2026-08-21 00:11:00'));
        $this->assertFalse($event->isDue(app()));
    }
}
