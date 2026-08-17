<?php

namespace Tests\Feature;

use App\Enums\ExerciseTypeCode;
use App\Enums\LangCode;
use App\Models\Exercise;
use App\Models\ExerciseComplete;
use App\Models\ExerciseItem;
use App\Models\ExerciseItemResult;
use App\Models\ExerciseType;
use App\Models\User;
use App\Models\UserWordRepetition;
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
            '2026-08-01T10:00:00Z',
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

    public function test_it_returns_current_week_and_month_charts_for_every_user(): void
    {
        $this->seed(LangSeeder::class);
        $now = CarbonImmutable::parse('2026-07-15T12:00:00Z');
        $this->travelTo($now);

        $alice = User::factory()->create(['name' => 'Alice']);
        $bob = User::factory()->create(['name' => 'Bob']);
        $charlie = User::factory()->create(['name' => 'Charlie']);
        $dailyType = ExerciseType::query()->create([
            'name' => 'daily-chart',
            'title' => 'Daily chart exercise',
        ]);
        $userType = ExerciseType::forCode(ExerciseTypeCode::user);

        $aliceWeekExercise = $this->createExercise(
            $alice,
            $dailyType,
            '2026-07-14T09:00:00Z',
            3,
        );
        $this->createBidirectionalCompletion(
            $aliceWeekExercise,
            '2026-07-14T10:00:00Z',
            [[0, 0], [0, 1], [1, 1]],
        );
        $attentionWord = $aliceWeekExercise
            ->items()
            ->orderBy('id')
            ->get()
            ->get(2)
            ->word;
        UserWordRepetition::query()->create([
            'user_id' => $alice->id,
            'word_id' => $attentionWord->id,
            'is_active' => true,
        ]);

        $aliceMonthExercise = $this->createExercise(
            $alice,
            $dailyType,
            '2026-07-05T09:00:00Z',
            1,
        );
        $this->createBidirectionalCompletion(
            $aliceMonthExercise,
            '2026-07-05T10:00:00Z',
            [[0, 0]],
        );

        $aliceUserExercise = $this->createExercise(
            $alice,
            $userType,
            '2026-07-14T11:00:00Z',
            1,
        );
        $this->createBidirectionalCompletion(
            $aliceUserExercise,
            '2026-07-14T12:00:00Z',
            [[0, 0]],
        );

        $bobExercise = $this->createExercise(
            $bob,
            $dailyType,
            '2026-07-13T09:00:00Z',
            1,
        );
        $this->createBidirectionalCompletion(
            $bobExercise,
            '2026-07-13T10:00:00Z',
            [[0, 0]],
        );

        $response = $this->withToken($this->accessToken($alice))
            ->getJson(
                '/api/v1/exercises/statistics'
                .'?dateFrom=2026-07-01T00:00:00Z'
                .'&dateTo=2026-07-31T23:59:59Z',
            )
            ->assertOk()
            ->assertJsonCount(3, 'charts.week.users')
            ->assertJsonCount(3, 'charts.month.users')
            ->assertJsonCount(1, 'attentionWords')
            ->assertJsonPath('attentionWords.0.wordId', $attentionWord->id)
            ->assertJsonPath(
                'attentionWords.0.russian',
                $attentionWord->ru,
            )
            ->assertJsonPath(
                'attentionWords.0.english',
                $attentionWord->en,
            )
            ->assertJsonPath('attentionWords.0.errorPercentage', 100)
            ->assertJsonPath(
                'attentionWords.0.isSelectedForRepetition',
                true,
            );

        $week = collect($response->json('charts.week.users'))
            ->keyBy('userId');
        $month = collect($response->json('charts.month.users'))
            ->keyBy('userId');

        $this->assertSame([
            'userId' => $alice->id,
            'userName' => 'Alice',
            'learnedWords' => 2,
            'wordsToRepeat' => 1,
            'completedExercises' => 2,
        ], $week[$alice->id]);
        $this->assertSame([
            'userId' => $alice->id,
            'userName' => 'Alice',
            'learnedWords' => 3,
            'wordsToRepeat' => 1,
            'completedExercises' => 3,
        ], $month[$alice->id]);
        $this->assertSame([
            'userId' => $bob->id,
            'userName' => 'Bob',
            'learnedWords' => 1,
            'wordsToRepeat' => 0,
            'completedExercises' => 1,
        ], $week[$bob->id]);
        $this->assertSame([
            'userId' => $charlie->id,
            'userName' => 'Charlie',
            'learnedWords' => 0,
            'wordsToRepeat' => 0,
            'completedExercises' => 0,
        ], $week[$charlie->id]);

        $this->assertTrue(
            CarbonImmutable::parse($response->json('charts.week.dateFrom'))
                ->equalTo('2026-07-13T00:00:00Z'),
        );
        $this->assertTrue(
            CarbonImmutable::parse($response->json('charts.month.dateFrom'))
                ->equalTo('2026-07-01T00:00:00Z'),
        );
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
        ?string $receivedAt = null,
    ): ExerciseComplete {
        $receivedAt ??= $completedAt;
        $completion = new ExerciseComplete([
            'exercise_id' => $exercise->id,
            'completed_at' => CarbonImmutable::parse($completedAt),
        ]);
        $completion->created_at = CarbonImmutable::parse($receivedAt);
        $completion->updated_at = CarbonImmutable::parse($receivedAt);
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

    /**
     * @param  list<array{0: int, 1: int}>  $errorsByWord
     */
    private function createBidirectionalCompletion(
        Exercise $exercise,
        string $completedAt,
        array $errorsByWord,
    ): ExerciseComplete {
        $completion = new ExerciseComplete([
            'exercise_id' => $exercise->id,
            'completed_at' => CarbonImmutable::parse($completedAt),
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
                foreach ([LangCode::en, LangCode::ru] as $langIndex => $lang) {
                    $result = new ExerciseItemResult([
                        'exercise_complete_id' => $completion->id,
                        'exercise_item_id' => $item->id,
                        'errors_count' => $errorsByWord[$index][$langIndex],
                        'hints_count' => 0,
                        'lang_id' => $lang->value,
                        'variants' => [],
                    ]);
                    $result->created_at = CarbonImmutable::parse($completedAt);
                    $result->updated_at = CarbonImmutable::parse($completedAt);
                    $result->save();
                }
            });

        return $completion;
    }

    private function accessToken(User $user): string
    {
        return app(AuthTokenService::class)->issue($user)['accessToken'];
    }
}
