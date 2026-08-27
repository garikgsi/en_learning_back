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
            ->create();

        $users[0]->info()->create([
            'first_grade_year' => now()->year - 5,
        ]);
        $users[1]->info()->create([
            'first_grade_year' => now()->year - 6,
        ]);
        $testUser = User::factory()->create(['is_test' => true]);
        $testUser->info()->create([
            'first_grade_year' => now()->year - 5,
        ]);

        foreach (range(1, 20) as $number) {
            Word::query()->create([
                'ru' => "слово{$number}",
                'en' => "word{$number}",
                'grade' => 5,
            ]);
        }

        $this->artisan('exercises:create-daily')
            ->assertSuccessful();
        $this->artisan('exercises:create-daily')
            ->assertSuccessful();

        $this->assertDatabaseCount('exercise', 2);
        $this->assertDatabaseCount('user_notifications', 2);
        $this->assertDatabaseMissing('exercise', [
            'user_id' => $testUser->id,
        ]);

        foreach ($users as $index => $user) {
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
            $this->assertCount($index === 0 ? 10 : 15, $exercise->items);
            $this->assertDatabaseHas('user_notifications', [
                'user_id' => $user->id,
                'type' => 'exercise.created',
            ]);
        }
    }

    public function test_it_is_scheduled_at_noon_in_moscow_from_monday_to_thursday(): void
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
            '2026-07-27 09:00:00 UTC',
            '2026-07-28 09:00:00 UTC',
            '2026-07-29 09:00:00 UTC',
            '2026-07-30 09:00:00 UTC',
        ] as $scheduledDate) {
            $this->travelTo(CarbonImmutable::parse($scheduledDate));
            $this->assertTrue($event->isDue(app()));
        }

        foreach ([
            '2026-07-31 09:00:00 UTC',
            '2026-08-01 09:00:00 UTC',
            '2026-08-02 09:00:00 UTC',
            '2026-08-03 09:01:00 UTC',
        ] as $unscheduledDate) {
            $this->travelTo(CarbonImmutable::parse($unscheduledDate));
            $this->assertFalse($event->isDue(app()));
        }
    }
}
