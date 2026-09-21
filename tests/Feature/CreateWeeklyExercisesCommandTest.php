<?php

namespace Tests\Feature;

use App\Enums\ExerciseTypeCode;
use App\Models\Exercise;
use App\Models\ExerciseType;
use App\Models\User;
use App\Models\Word;
use Carbon\CarbonImmutable;
use Database\Seeders\ExerciseTypesSeeder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateWeeklyExercisesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_weekly_exercises_from_unique_daily_words(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-07-31 00:00:00'));
        $this->seed(ExerciseTypesSeeder::class);

        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();
        $testUser = User::factory()->create(['is_test' => true]);
        $words = collect(range(1, 4))->map(
            fn (int $number): Word => Word::query()->create([
                'ru' => "слово {$number}",
                'en' => "word {$number}",
                'grade' => 1,
            ]),
        );

        $this->createDailyExercise(
            $firstUser,
            '2026-07-27 00:00:00',
            [$words[0]->id, $words[1]->id],
        );
        $this->createDailyExercise(
            $firstUser,
            '2026-07-30 00:00:00',
            [$words[1]->id, $words[2]->id],
        );
        $this->createDailyExercise(
            $firstUser,
            '2026-07-20 00:00:00',
            [$words[3]->id],
        );
        $this->createDailyExercise(
            $secondUser,
            '2026-07-28 00:00:00',
            [$words[3]->id],
        );
        $this->createDailyExercise(
            $testUser,
            '2026-07-29 00:00:00',
            [$words[0]->id],
        );

        $this->artisan('exercises:create-weekly')
            ->assertSuccessful();
        $this->artisan('exercises:create-weekly')
            ->assertSuccessful();

        $weeklyType = ExerciseType::forCode(ExerciseTypeCode::weekly);
        $weeklyExercises = Exercise::query()
            ->where('type_id', $weeklyType->id)
            ->with('items')
            ->get()
            ->keyBy('user_id');

        $this->assertCount(2, $weeklyExercises);
        $this->assertDatabaseCount('user_notifications', 2);
        $this->assertArrayNotHasKey($testUser->id, $weeklyExercises);
        $this->assertEqualsCanonicalizing(
            $words->pluck('id')->all(),
            $weeklyExercises[$firstUser->id]
                ->items
                ->pluck('word_id')
                ->all(),
        );
        $this->assertSame(
            [$words[3]->id],
            $weeklyExercises[$secondUser->id]
                ->items
                ->pluck('word_id')
                ->all(),
        );
        $this->assertTrue(
            $weeklyExercises[$firstUser->id]
                ->dueDate
                ->equalTo('2026-07-31 00:00:00'),
        );
    }

    public function test_it_is_scheduled_at_noon_in_moscow_each_friday(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(
                fn ($event): bool => str_contains(
                    $event->command,
                    'exercises:create-weekly',
                ),
            );

        $this->assertNotNull($event);

        $this->travelTo(CarbonImmutable::parse('2026-07-31 09:00:00 UTC'));
        $this->assertTrue($event->isDue(app()));

        $this->travelTo(CarbonImmutable::parse('2026-07-31 09:01:00 UTC'));
        $this->assertFalse($event->isDue(app()));

        $this->travelTo(CarbonImmutable::parse('2026-07-30 09:00:00 UTC'));
        $this->assertFalse($event->isDue(app()));
    }

    public function test_second_week_contains_only_that_weeks_daily_words_when_previous_exercises_are_uncompleted(): void
    {
        $this->seed(ExerciseTypesSeeder::class);

        $user = User::factory()->create();
        $words = collect(range(1, 8))->map(
            fn (int $number): Word => Word::query()->create([
                'ru' => "слово {$number}",
                'en' => "word {$number}",
                'grade' => 1,
            ]),
        );

        foreach (range(0, 3) as $day) {
            $this->createDailyExercise(
                $user,
                CarbonImmutable::parse('2026-07-20')->addDays($day)->toDateString(),
                [$words[$day]->id],
            );
        }

        $this->travelTo(CarbonImmutable::parse('2026-07-24 12:00:00'));
        $this->artisan('exercises:create-weekly')->assertSuccessful();

        foreach (range(0, 3) as $day) {
            $this->createDailyExercise(
                $user,
                CarbonImmutable::parse('2026-07-27')->addDays($day)->toDateString(),
                [$words[$day + 4]->id],
            );
        }

        $this->travelTo(CarbonImmutable::parse('2026-07-31 12:00:00'));
        $this->artisan('exercises:create-weekly')->assertSuccessful();

        $weeklyType = ExerciseType::forCode(ExerciseTypeCode::weekly);
        $secondWeekWords = Exercise::query()
            ->where('user_id', $user->id)
            ->where('type_id', $weeklyType->id)
            ->whereDate('dueDate', '2026-07-31')
            ->sole()
            ->items()
            ->pluck('word_id')
            ->all();

        $this->assertEqualsCanonicalizing(
            $words->slice(4)->pluck('id')->all(),
            $secondWeekWords,
        );
        $this->assertEmpty(
            array_intersect(
                $words->take(4)->pluck('id')->all(),
                $secondWeekWords,
            ),
        );
    }

    /**
     * @param  array<int, int>  $wordIds
     */
    private function createDailyExercise(
        User $user,
        string $dueDate,
        array $wordIds,
    ): Exercise {
        $exercise = Exercise::query()->create([
            'user_id' => $user->id,
            'type_id' => ExerciseTypeCode::daily->value,
            'dueDate' => $dueDate,
        ]);
        $exercise->forceFill([
            'created_at' => CarbonImmutable::parse($dueDate),
            'updated_at' => CarbonImmutable::parse($dueDate),
        ])->saveQuietly();
        $exercise->items()->createMany(
            array_map(
                fn (int $wordId): array => ['word_id' => $wordId],
                $wordIds,
            ),
        );

        return $exercise;
    }
}
