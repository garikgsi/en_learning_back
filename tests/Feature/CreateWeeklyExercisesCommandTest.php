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
        User::factory()->create();
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
        $this->assertEqualsCanonicalizing(
            [$words[0]->id, $words[1]->id, $words[2]->id],
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

    public function test_it_is_scheduled_at_midnight_each_friday(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(
                fn ($event): bool => str_contains(
                    $event->command,
                    'exercises:create-weekly',
                ),
            );

        $this->assertNotNull($event);

        $this->travelTo(CarbonImmutable::parse('2026-07-31 00:00:00'));
        $this->assertTrue($event->isDue(app()));

        $this->travelTo(CarbonImmutable::parse('2026-07-31 00:01:00'));
        $this->assertFalse($event->isDue(app()));

        $this->travelTo(CarbonImmutable::parse('2026-07-30 00:00:00'));
        $this->assertFalse($event->isDue(app()));
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
        $exercise->items()->createMany(
            array_map(
                fn (int $wordId): array => ['word_id' => $wordId],
                $wordIds,
            ),
        );

        return $exercise;
    }
}
