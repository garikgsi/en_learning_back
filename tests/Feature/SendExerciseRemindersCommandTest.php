<?php

namespace Tests\Feature;

use App\Enums\ExerciseTypeCode;
use App\Models\Exercise;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Seeders\ExerciseTypesSeeder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SendExerciseRemindersCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_reminds_only_about_uncompleted_daily_and_weekly_exercises_due_today(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-07-31 15:00:00 UTC'));
        $this->seed(ExerciseTypesSeeder::class);
        $user = User::factory()->create();
        $daily = $this->createExercise($user, ExerciseTypeCode::daily, today());
        $weekly = $this->createExercise($user, ExerciseTypeCode::weekly, today());
        $completed = $this->createExercise($user, ExerciseTypeCode::daily, today());
        $completed->completions()->create();
        $this->createExercise($user, ExerciseTypeCode::user, today());
        $this->createExercise(
            $user,
            ExerciseTypeCode::daily,
            today()->subDay(),
        );

        $this->artisan('exercises:send-reminders')
            ->expectsOutput('Exercise reminders created: 2.')
            ->assertSuccessful();
        $this->artisan('exercises:send-reminders')
            ->expectsOutput('Exercise reminders created: 0.')
            ->assertSuccessful();

        $this->assertDatabaseCount('user_notifications', 2);
        $this->assertDatabaseHas('user_notifications', [
            'type' => 'exercise.reminder',
            'deduplication_key' => "exercise:{$daily->id}:reminder",
        ]);
        $this->assertDatabaseHas('user_notifications', [
            'type' => 'exercise.reminder',
            'deduplication_key' => "exercise:{$weekly->id}:reminder",
        ]);
    }

    public function test_it_is_scheduled_at_six_pm_in_moscow_every_day(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(
                fn ($event): bool => str_contains(
                    $event->command,
                    'exercises:send-reminders',
                ),
            );

        $this->assertNotNull($event);

        $this->travelTo(CarbonImmutable::parse('2026-07-31 15:00:00 UTC'));
        $this->assertTrue($event->isDue(app()));

        $this->travelTo(CarbonImmutable::parse('2026-07-31 15:01:00 UTC'));
        $this->assertFalse($event->isDue(app()));
    }

    private function createExercise(
        User $user,
        ExerciseTypeCode $type,
        CarbonInterface $dueDate,
    ): Exercise {
        return Exercise::query()->create([
            'user_id' => $user->id,
            'type_id' => $type->value,
            'dueDate' => $dueDate,
        ]);
    }
}
