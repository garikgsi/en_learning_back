<?php

namespace Tests\Feature;

use App\Enums\ExerciseTypeCode;
use App\Enums\LangCode;
use App\Models\Exercise;
use App\Models\User;
use App\Models\UserWordRepetition;
use App\Models\Word;
use App\Services\Auth\AuthTokenService;
use Database\Seeders\ExerciseTypesSeeder;
use Database\Seeders\LangSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ExerciseCompleteControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_save_exercise_completion_results(): void
    {
        $this->seed([ExerciseTypesSeeder::class, LangSeeder::class]);

        $user = User::factory()->create();
        $exercise = $this->createExercise($user);
        $word = $this->createWord();
        $item = $exercise->items()->create([
            'word_id' => $word->id,
        ]);
        $repetition = UserWordRepetition::query()->create([
            'user_id' => $user->id,
            'word_id' => $word->id,
            'is_active' => true,
        ]);

        $attemptId = (string) Str::uuid();
        $completedAt = now()->subHour()->toISOString();

        $this->withToken($this->accessToken($user))
            ->postJson('/api/v1/exercises/complete', [
                'attempt_id' => $attemptId,
                'completed_at' => $completedAt,
                'exercise_id' => $exercise->id,
                'exercise_items_result' => [[
                    'exercise_item_id' => $item->id,
                    'errors_count' => 2,
                    'hints_count' => 1,
                    'lang_id' => LangCode::en->value,
                    'variants' => [],
                ]],
            ])
            ->assertCreated()
            ->assertJsonPath('data.exercise_id', $exercise->id)
            ->assertJsonPath('data.attempt_id', $attemptId)
            ->assertJsonPath(
                'data.exercise_items_result.0.variants',
                [],
            );

        $completeId = $exercise->completions()->sole()->id;
        $this->assertDatabaseHas('exercise_items_result', [
            'exercise_complete_id' => $completeId,
            'exercise_item_id' => $item->id,
            'errors_count' => 2,
            'hints_count' => 1,
            'lang_id' => LangCode::en->value,
        ]);
        $this->assertDatabaseHas('exercise_complete', [
            'id' => $completeId,
            'client_attempt_id' => $attemptId,
        ]);
        $this->assertFalse($repetition->refresh()->is_active);
    }

    public function test_result_item_must_belong_to_completed_exercise(): void
    {
        $this->seed([ExerciseTypesSeeder::class, LangSeeder::class]);

        $user = User::factory()->create();
        $exercise = $this->createExercise($user);
        $otherExercise = $this->createExercise($user);
        $otherItem = $otherExercise->items()->create([
            'word_id' => $this->createWord()->id,
        ]);

        $this->withToken($this->accessToken($user))
            ->postJson('/api/v1/exercises/complete', [
                'attempt_id' => (string) Str::uuid(),
                'completed_at' => now()->toISOString(),
                'exercise_id' => $exercise->id,
                'exercise_items_result' => [[
                    'exercise_item_id' => $otherItem->id,
                    'errors_count' => 0,
                    'hints_count' => 0,
                    'lang_id' => LangCode::ru->value,
                    'variants' => ['кот'],
                ]],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(
                'exercise_items_result.0.exercise_item_id',
            );

        $this->assertDatabaseCount('exercise_complete', 0);
    }

    public function test_user_cannot_complete_another_users_exercise(): void
    {
        $this->seed([ExerciseTypesSeeder::class, LangSeeder::class]);

        $user = User::factory()->create();
        $exercise = $this->createExercise(User::factory()->create());
        $item = $exercise->items()->create([
            'word_id' => $this->createWord()->id,
        ]);

        $this->withToken($this->accessToken($user))
            ->postJson('/api/v1/exercises/complete', [
                'attempt_id' => (string) Str::uuid(),
                'completed_at' => now()->toISOString(),
                'exercise_id' => $exercise->id,
                'exercise_items_result' => [[
                    'exercise_item_id' => $item->id,
                    'errors_count' => 0,
                    'hints_count' => 0,
                    'lang_id' => LangCode::en->value,
                    'variants' => ['cat'],
                ]],
            ])
            ->assertNotFound();
    }

    public function test_completion_payload_is_validated(): void
    {
        $this->seed([ExerciseTypesSeeder::class, LangSeeder::class]);

        $user = User::factory()->create();
        $exercise = $this->createExercise($user);
        $item = $exercise->items()->create([
            'word_id' => $this->createWord()->id,
        ]);

        $this->withToken($this->accessToken($user))
            ->postJson('/api/v1/exercises/complete', [
                'attempt_id' => 'not-a-uuid',
                'completed_at' => now()->subDays(15)->toISOString(),
                'exercise_id' => $exercise->id,
                'exercise_items_result' => [[
                    'exercise_item_id' => $item->id,
                    'errors_count' => -1,
                    'hints_count' => 0,
                    'lang_id' => 999,
                    'variants' => [10],
                ]],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'attempt_id',
                'completed_at',
                'exercise_items_result.0.errors_count',
                'exercise_items_result.0.lang_id',
                'exercise_items_result.0.variants.0',
            ]);
    }

    public function test_user_exercise_can_only_be_completed_once_and_counts_as_repetition(): void
    {
        $this->seed([ExerciseTypesSeeder::class, LangSeeder::class]);

        $user = User::factory()->create();
        $exercise = Exercise::query()->create([
            'user_id' => $user->id,
            'type_id' => ExerciseTypeCode::user->value,
            'dueDate' => now(),
        ]);
        $word = $this->createWord();
        $item = $exercise->items()->create(['word_id' => $word->id]);
        $repetition = UserWordRepetition::query()->create([
            'user_id' => $user->id,
            'word_id' => $word->id,
            'is_active' => true,
        ]);
        $payload = [
            'attempt_id' => (string) Str::uuid(),
            'completed_at' => now()->toISOString(),
            'exercise_id' => $exercise->id,
            'exercise_items_result' => [[
                'exercise_item_id' => $item->id,
                'errors_count' => 0,
                'hints_count' => 0,
                'lang_id' => LangCode::en->value,
                'variants' => ['cat'],
            ]],
        ];

        $this->withToken($this->accessToken($user))
            ->postJson('/api/v1/exercises/complete', $payload)
            ->assertCreated();

        $this->assertFalse($repetition->refresh()->is_active);

        $this->postJson('/api/v1/exercises/complete', $payload)
            ->assertOk();

        $payload['attempt_id'] = (string) Str::uuid();

        $this->postJson('/api/v1/exercises/complete', $payload)
            ->assertConflict()
            ->assertJsonPath('code', 'USER_EXERCISE_ALREADY_COMPLETED');

        $this->getJson("/api/v1/exercises/{$exercise->id}")
            ->assertConflict()
            ->assertJsonPath('code', 'USER_EXERCISE_ALREADY_COMPLETED');

        $this->assertDatabaseCount('exercise_complete', 1);
    }

    public function test_repeated_attempt_returns_original_completion_without_duplicate(): void
    {
        $this->seed([ExerciseTypesSeeder::class, LangSeeder::class]);

        $user = User::factory()->create();
        $exercise = $this->createExercise($user);
        $item = $exercise->items()->create([
            'word_id' => $this->createWord()->id,
        ]);
        $payload = [
            'attempt_id' => (string) Str::uuid(),
            'completed_at' => now()->subMinute()->toISOString(),
            'exercise_id' => $exercise->id,
            'exercise_items_result' => [[
                'exercise_item_id' => $item->id,
                'errors_count' => 0,
                'hints_count' => 0,
                'lang_id' => LangCode::en->value,
                'variants' => ['cat'],
            ]],
        ];

        $firstResponse = $this->withToken($this->accessToken($user))
            ->postJson('/api/v1/exercises/complete', $payload)
            ->assertCreated();

        $this->postJson('/api/v1/exercises/complete', $payload)
            ->assertOk()
            ->assertJsonPath('data.id', $firstResponse->json('data.id'));

        $this->assertDatabaseCount('exercise_complete', 1);
        $this->assertDatabaseCount('exercise_items_result', 1);
    }

    public function test_attempt_id_cannot_be_reused_with_different_result(): void
    {
        $this->seed([ExerciseTypesSeeder::class, LangSeeder::class]);

        $user = User::factory()->create();
        $exercise = $this->createExercise($user);
        $item = $exercise->items()->create([
            'word_id' => $this->createWord()->id,
        ]);
        $payload = [
            'attempt_id' => (string) Str::uuid(),
            'completed_at' => now()->toISOString(),
            'exercise_id' => $exercise->id,
            'exercise_items_result' => [[
                'exercise_item_id' => $item->id,
                'errors_count' => 0,
                'hints_count' => 0,
                'lang_id' => LangCode::en->value,
                'variants' => [],
            ]],
        ];

        $this->withToken($this->accessToken($user))
            ->postJson('/api/v1/exercises/complete', $payload)
            ->assertCreated();

        $payload['exercise_items_result'][0]['errors_count'] = 1;

        $this->postJson('/api/v1/exercises/complete', $payload)
            ->assertConflict()
            ->assertJsonPath('code', 'IDEMPOTENCY_KEY_REUSED');

        $this->assertDatabaseCount('exercise_complete', 1);
        $this->assertDatabaseHas('exercise_items_result', [
            'errors_count' => 0,
        ]);
    }

    private function createExercise(User $user): Exercise
    {
        return Exercise::query()->create([
            'user_id' => $user->id,
            'type_id' => ExerciseTypeCode::daily->value,
            'dueDate' => now(),
        ]);
    }

    private function createWord(): Word
    {
        return Word::query()->create([
            'ru' => 'кот',
            'en' => 'cat',
            'grade' => 1,
        ]);
    }

    private function accessToken(User $user): string
    {
        return app(AuthTokenService::class)->issue($user)['accessToken'];
    }
}
