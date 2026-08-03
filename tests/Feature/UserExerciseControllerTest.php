<?php

namespace Tests\Feature;

use App\Enums\ExerciseTypeCode;
use App\Models\Exercise;
use App\Models\User;
use App\Models\Word;
use App\Services\Auth\AuthTokenService;
use Carbon\CarbonImmutable;
use Database\Seeders\ExerciseTypesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserExerciseControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_an_exercise_for_the_current_date(): void
    {
        $this->seed(ExerciseTypesSeeder::class);
        $now = CarbonImmutable::parse('2026-07-31T14:30:00Z');
        $this->travelTo($now);

        $user = $this->userWithGrade(3);
        $otherUser = $this->userWithGrade(3);

        foreach (range(1, 20) as $index) {
            Word::query()->create([
                'ru' => "слово-{$index}",
                'en' => "word-{$index}",
                'grade' => 3,
            ]);
        }

        $this->withToken($this->accessToken($user))
            ->postJson('/api/v1/exercises')
            ->assertCreated()
            ->assertJsonPath('item.userId', $user->id)
            ->assertJsonPath('item.type.id', ExerciseTypeCode::user->value)
            ->assertJsonPath('item.type.name', ExerciseTypeCode::user->name)
            ->assertJsonPath('item.type.title', 'Пользовательское')
            ->assertJsonCount(15, 'item.items');

        $exercise = Exercise::query()->sole();

        $this->assertSame($user->id, $exercise->user_id);
        $this->assertSame(ExerciseTypeCode::user->value, $exercise->type_id);
        $this->assertTrue($exercise->dueDate->equalTo($now->startOfDay()));
        $this->assertNotSame($otherUser->id, $exercise->user_id);
    }

    public function test_user_info_is_required_to_create_an_exercise(): void
    {
        $this->seed(ExerciseTypesSeeder::class);
        $user = User::factory()->create();

        $this->withToken($this->accessToken($user))
            ->postJson('/api/v1/exercises')
            ->assertConflict()
            ->assertJsonPath('code', 'USER_INFO_REQUIRED');

        $this->assertDatabaseCount('exercise', 0);
    }

    public function test_exercise_is_not_created_when_dictionary_has_no_words(): void
    {
        $this->seed(ExerciseTypesSeeder::class);
        $user = $this->userWithGrade(3);

        $this->withToken($this->accessToken($user))
            ->postJson('/api/v1/exercises')
            ->assertConflict()
            ->assertJsonPath('message', 'Нет слов в словаре')
            ->assertJsonPath('code', 'DICTIONARY_EMPTY');

        $this->assertDatabaseCount('exercise', 0);
    }

    public function test_creation_requires_authentication(): void
    {
        $this->postJson('/api/v1/exercises')
            ->assertUnauthorized();
    }

    private function userWithGrade(int $grade): User
    {
        $user = User::factory()->create();
        $user->info()->create([
            'first_grade_year' => now()->year - $grade,
        ]);

        return $user;
    }

    private function accessToken(User $user): string
    {
        return app(AuthTokenService::class)->issue($user)['accessToken'];
    }
}
