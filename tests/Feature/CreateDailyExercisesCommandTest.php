<?php

namespace Tests\Feature;

use App\Enums\ExerciseTypeCode;
use App\Models\Exercise;
use App\Models\User;
use App\Models\Word;
use Carbon\CarbonImmutable;
use Database\Seeders\ExerciseTypesSeeder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateDailyExercisesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_one_daily_exercise_for_every_user(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-07-28 00:00:00'));
        $this->seed(ExerciseTypesSeeder::class);

        $users = User::factory()
            ->count(2)
            ->create()
            ->each(fn (User $user) => $user->info()->create([
                'first_grade_year' => now()->year - 3,
            ]));

        foreach (range(1, 5) as $number) {
            Word::query()->create([
                'ru' => "слово{$number}",
                'en' => "word{$number}",
                'grade' => 3,
            ]);
        }

        $this->artisan('exercises:create-daily')
            ->assertSuccessful();
        $this->artisan('exercises:create-daily')
            ->assertSuccessful();

        $this->assertDatabaseCount('exercise', 2);

        foreach ($users as $user) {
            $exercise = Exercise::query()
                ->where('user_id', $user->id)
                ->sole();

            $this->assertSame(
                ExerciseTypeCode::daily->value,
                $exercise->type_id,
            );
            $this->assertTrue(
                $exercise->dueDate->equalTo('2026-07-28 00:00:00'),
            );
            $this->assertCount(5, $exercise->items);
        }
    }

    public function test_it_is_scheduled_at_midnight_from_monday_to_thursday(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(
                fn ($event): bool => str_contains(
                    $event->command,
                    'exercises:create-daily',
                ),
            );

        $this->assertNotNull($event);

        foreach ([
            '2026-07-27 00:00:00',
            '2026-07-28 00:00:00',
            '2026-07-29 00:00:00',
            '2026-07-30 00:00:00',
        ] as $scheduledDate) {
            $this->travelTo(CarbonImmutable::parse($scheduledDate));
            $this->assertTrue($event->isDue(app()));
        }

        foreach ([
            '2026-07-31 00:00:00',
            '2026-08-01 00:00:00',
            '2026-08-02 00:00:00',
            '2026-08-03 00:01:00',
        ] as $unscheduledDate) {
            $this->travelTo(CarbonImmutable::parse($unscheduledDate));
            $this->assertFalse($event->isDue(app()));
        }
    }
}
