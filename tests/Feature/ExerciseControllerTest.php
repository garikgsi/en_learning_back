<?php

namespace Tests\Feature;

use App\Models\Exercise;
use App\Models\ExerciseType;
use App\Models\User;
use App\Services\Auth\AuthTokenService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExerciseControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_current_users_exercises_for_inclusive_period(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $type = $this->createType();

        $first = $this->createExercise($user, $type, '2026-07-29T10:00:00Z');
        $second = $this->createExercise($user, $type, '2026-07-29T12:00:00Z');
        $this->createExercise($user, $type, '2026-07-30T10:00:00Z');
        $this->createExercise($otherUser, $type, '2026-07-29T11:00:00Z');

        $this->withToken($this->accessToken($user))
            ->getJson(
                '/api/v1/exercises'
                .'?dateFrom=2026-07-29T10:00:00Z'
                .'&dateTo=2026-07-29T12:00:00Z',
            )
            ->assertOk()
            ->assertJsonCount(2, 'items')
            ->assertJsonPath('items.0.id', $first->id)
            ->assertJsonPath('items.1.id', $second->id)
            ->assertJsonPath('items.0.type.id', $type->id);
    }

    public function test_it_returns_only_exercises_due_today(): void
    {
        $now = CarbonImmutable::parse('2026-07-29T12:00:00Z');
        $this->travelTo($now);

        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $type = $this->createType();

        $first = $this->createExercise($user, $type, $now->startOfDay());
        $second = $this->createExercise($user, $type, $now->endOfDay());
        $this->createExercise($user, $type, $now->subDay());
        $this->createExercise($user, $type, $now->addDay());
        $this->createExercise($otherUser, $type, $now);

        $this->withToken($this->accessToken($user))
            ->getJson('/api/v1/exercises/current')
            ->assertOk()
            ->assertJsonCount(2, 'items')
            ->assertJsonPath('items.0.id', $first->id)
            ->assertJsonPath('items.1.id', $second->id);
    }

    public function test_period_requires_both_dates_in_valid_order(): void
    {
        $user = User::factory()->create();

        $this->withToken($this->accessToken($user))
            ->getJson(
                '/api/v1/exercises'
                .'?dateFrom=2026-07-30T00:00:00Z'
                .'&dateTo=2026-07-29T00:00:00Z',
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('dateTo');
    }

    public function test_exercise_endpoints_require_authentication(): void
    {
        $this->getJson(
            '/api/v1/exercises'
            .'?dateFrom=2026-07-29T00:00:00Z'
            .'&dateTo=2026-07-30T00:00:00Z',
        )->assertUnauthorized();

        $this->getJson('/api/v1/exercises/current')
            ->assertUnauthorized();
    }

    private function createType(): ExerciseType
    {
        return ExerciseType::query()->create([
            'name' => 'translation',
            'title' => 'Translate the word',
        ]);
    }

    private function createExercise(
        User $user,
        ExerciseType $type,
        CarbonImmutable|string $dueDate,
    ): Exercise {
        return Exercise::query()->create([
            'user_id' => $user->id,
            'type_id' => $type->id,
            'dueDate' => $dueDate,
        ]);
    }

    private function accessToken(User $user): string
    {
        return app(AuthTokenService::class)->issue($user)['accessToken'];
    }
}
