<?php

namespace Tests\Feature;

use App\Enums\LangCode;
use App\Models\Exercise;
use App\Models\ExerciseComplete;
use App\Models\ExerciseItem;
use App\Models\ExerciseItemResult;
use App\Models\ExerciseType;
use App\Models\User;
use App\Models\Word;
use App\Services\Auth\AuthTokenService;
use Carbon\CarbonImmutable;
use Database\Seeders\LangSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExerciseStatisticsControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_uncompleted_exercises_and_every_completion_for_period(): void
    {
        $this->seed(LangSeeder::class);

        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $type = ExerciseType::query()->create([
            'name' => 'daily',
            'title' => 'Daily exercise',
        ]);

        $uncompleted = $this->createExercise(
            $user,
            $type,
            '2026-07-03T09:00:00Z',
            3,
        );
        $completed = $this->createExercise(
            $user,
            $type,
            '2026-07-04T09:00:00Z',
            2,
        );
        $firstCompletion = $this->createCompletion(
            $completed,
            '2026-07-10T10:00:00Z',
            [3, 0],
        );
        $secondCompletion = $this->createCompletion(
            $completed,
            '2026-07-12T10:00:00Z',
            [0, 0],
        );

        $completedOutsidePeriod = $this->createExercise(
            $user,
            $type,
            '2026-07-05T09:00:00Z',
            1,
        );
        $this->createCompletion(
            $completedOutsidePeriod,
            '2026-06-30T10:00:00Z',
            [0],
        );

        $otherExercise = $this->createExercise(
            $otherUser,
            $type,
            '2026-07-06T09:00:00Z',
            1,
        );
        $this->createCompletion(
            $otherExercise,
            '2026-07-11T10:00:00Z',
            [0],
        );

        $this->withToken($this->accessToken($user))
            ->getJson(
                '/api/v1/exercises/statistics'
                .'?dateFrom=2026-07-01T00:00:00Z'
                .'&dateTo=2026-07-31T23:59:59Z',
            )
            ->assertOk()
            ->assertJsonCount(3, 'items')
            ->assertJsonPath('items.0.exerciseId', $uncompleted->id)
            ->assertJsonPath('items.0.completionId', null)
            ->assertJsonPath('items.0.status', 'uncompleted')
            ->assertJsonPath('items.0.wordsCount', 3)
            ->assertJsonPath('items.1.completionId', $firstCompletion->id)
            ->assertJsonPath('items.1.status', 'completed')
            ->assertJsonPath('items.1.wordsCount', 2)
            ->assertJsonPath('items.1.wordsWithErrors', 1)
            ->assertJsonPath('items.1.successPercentage', 50)
            ->assertJsonPath('items.2.completionId', $secondCompletion->id)
            ->assertJsonPath('items.2.wordsWithErrors', 0)
            ->assertJsonPath('items.2.successPercentage', 100);
    }

    public function test_statistics_period_is_validated_and_requires_authentication(): void
    {
        $user = User::factory()->create();

        $this->withToken($this->accessToken($user))
            ->getJson(
                '/api/v1/exercises/statistics'
                .'?dateFrom=2026-07-31T00:00:00Z'
                .'&dateTo=2026-07-01T00:00:00Z',
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('dateTo');

        $this->withHeader('Authorization', '')
            ->getJson(
                '/api/v1/exercises/statistics'
                .'?dateFrom=2026-07-01T00:00:00Z'
                .'&dateTo=2026-07-31T23:59:59Z',
            )
            ->assertUnauthorized();
    }

    private function createExercise(
        User $user,
        ExerciseType $type,
        string $dueDate,
        int $wordsCount,
    ): Exercise {
        $exercise = Exercise::query()->create([
            'user_id' => $user->id,
            'type_id' => $type->id,
            'dueDate' => $dueDate,
        ]);

        foreach (range(1, $wordsCount) as $index) {
            $word = Word::query()->create([
                'ru' => "слово {$exercise->id} {$index}",
                'en' => "word {$exercise->id} {$index}",
                'grade' => 1,
            ]);

            $exercise->items()->create([
                'word_id' => $word->id,
            ]);
        }

        return $exercise;
    }

    /**
     * @param  list<int>  $errorsByWord
     */
    private function createCompletion(
        Exercise $exercise,
        string $completedAt,
        array $errorsByWord,
    ): ExerciseComplete {
        $completion = new ExerciseComplete([
            'exercise_id' => $exercise->id,
        ]);
        $completion->created_at = CarbonImmutable::parse($completedAt);
        $completion->updated_at = CarbonImmutable::parse($completedAt);
        $completion->save();

        $exercise->items()
            ->orderBy('id')
            ->get()
            ->each(function (
                ExerciseItem $item,
                int $index,
            ) use ($completion, $errorsByWord, $completedAt): void {
                $result = new ExerciseItemResult([
                    'exercise_complete_id' => $completion->id,
                    'exercise_item_id' => $item->id,
                    'errors_count' => $errorsByWord[$index],
                    'hints_count' => 0,
                    'lang_id' => LangCode::en->value,
                    'variants' => [],
                ]);
                $result->created_at = CarbonImmutable::parse($completedAt);
                $result->updated_at = CarbonImmutable::parse($completedAt);
                $result->save();
            });

        return $completion;
    }

    private function accessToken(User $user): string
    {
        return app(AuthTokenService::class)->issue($user)['accessToken'];
    }
}
