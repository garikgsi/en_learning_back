<?php

namespace App\Services;

use App\Models\Exercise;
use App\Models\ExerciseComplete;
use App\Models\ExerciseItemResult;
use App\Models\User;
use App\Models\UserWordRepetition;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class ExerciseStatisticsService
{
    /**
     * @return Collection<int, array{
     *     wordId: int,
     *     russian: string,
     *     english: string,
     *     errorPercentage: int,
     *     isSelectedForRepetition: bool
     * }>
     */
    public function attentionWords(
        User $user,
        CarbonImmutable $now,
    ): Collection {
        $resultsByWord = ExerciseItemResult::query()
            ->whereHas(
                'complete',
                fn ($query) => $query
                    ->whereBetween('completed_at', [
                        $now->startOfMonth(),
                        $now->endOfDay(),
                    ])
                    ->whereHas(
                        'exercise',
                        fn ($query) => $query
                            ->where('user_id', $user->id),
                    ),
            )
            ->with('exerciseItem.word:id,ru,en')
            ->get()
            ->groupBy(
                fn (ExerciseItemResult $result): int => (int) $result
                    ->exerciseItem
                    ->word_id,
            );
        $selectedWordIds = UserWordRepetition::query()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->pluck('word_id')
            ->map(fn ($wordId): int => (int) $wordId)
            ->all();

        return $resultsByWord
            ->map(function (
                Collection $results,
                int $wordId,
            ) use ($selectedWordIds): ?array {
                $errorPercentage = $this->errorPercentage($results);

                if ($errorPercentage <= 50) {
                    return null;
                }

                /** @var ExerciseItemResult $firstResult */
                $firstResult = $results->first();
                $word = $firstResult->exerciseItem->word;

                return [
                    'wordId' => $wordId,
                    'russian' => $word->ru,
                    'english' => $word->en,
                    'errorPercentage' => (int) round($errorPercentage),
                    'isSelectedForRepetition' => in_array(
                        $wordId,
                        $selectedWordIds,
                        true,
                    ),
                ];
            })
            ->filter()
            ->sortBy([
                ['errorPercentage', 'desc'],
                ['russian', 'asc'],
            ])
            ->values();
    }

    /**
     * @return array{
     *     week: array{
     *         dateFrom: string,
     *         dateTo: string,
     *         users: Collection<int, array<string, int|string>>
     *     },
     *     month: array{
     *         dateFrom: string,
     *         dateTo: string,
     *         users: Collection<int, array<string, int|string>>
     *     }
     * }
     */
    public function charts(CarbonImmutable $now): array
    {
        $dateTo = $now->endOfDay();
        $weekDateFrom = $now->startOfWeek();
        $monthDateFrom = $now->startOfMonth();
        $queryDateFrom = $weekDateFrom->min($monthDateFrom);

        $users = User::query()
            ->orderBy('name')
            ->orderBy('id')
            ->get(['id', 'name']);
        $completions = ExerciseComplete::query()
            ->whereBetween('completed_at', [$queryDateFrom, $dateTo])
            ->with([
                'exercise:id,user_id,type_id',
                'itemResults.exerciseItem:id,word_id',
            ])
            ->get();

        return [
            'week' => $this->chartPeriod(
                $users,
                $completions->where('completed_at', '>=', $weekDateFrom),
                $weekDateFrom,
                $dateTo,
            ),
            'month' => $this->chartPeriod(
                $users,
                $completions->where('completed_at', '>=', $monthDateFrom),
                $monthDateFrom,
                $dateTo,
            ),
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function forPeriod(
        User $user,
        CarbonImmutable $dateFrom,
        CarbonImmutable $dateTo,
    ): Collection {
        $uncompletedExercises = Exercise::query()
            ->where('user_id', $user->id)
            ->whereBetween('dueDate', [$dateFrom, $dateTo])
            ->whereDoesntHave('completions')
            ->with([
                'type',
                'items.word:id,ru,en,ru_variants,en_variants,transcription',
            ])
            ->withCount('items')
            ->get()
            ->map(fn (Exercise $exercise): array => [
                'exerciseId' => $exercise->id,
                'createdAt' => $exercise->created_at->toISOString(),
                'completionId' => null,
                'status' => 'uncompleted',
                'date' => $exercise->dueDate->toISOString(),
                'type' => [
                    'id' => $exercise->type->id,
                    'name' => $exercise->type->name,
                    'title' => $exercise->type->title,
                ],
                'wordsCount' => $exercise->items_count,
                'wordsWithErrors' => 0,
                'errorsCount' => 0,
                'errorWords' => [],
                'words' => $exercise->items
                    ->sortBy('id')
                    ->map(fn ($item): array => [
                        'wordId' => $item->word->id,
                        'english' => $item->word->en,
                        'russian' => $item->word->ru,
                        'ruVariants' => $item->word->ru_variants ?? [],
                        'enVariants' => $item->word->en_variants ?? [],
                        'transcription' => $item->word->transcription,
                        'hasErrors' => false,
                    ])
                    ->values()
                    ->all(),
                'successPercentage' => 0,
            ]);

        $completions = ExerciseComplete::query()
            ->whereBetween('completed_at', [$dateFrom, $dateTo])
            ->whereHas(
                'exercise',
                fn ($query) => $query->where('user_id', $user->id),
            )
            ->with([
                'exercise.type',
                'exercise.items:id,exercise_id,word_id',
                'exercise.items.word:id,ru,en,ru_variants,en_variants,transcription',
                'itemResults:id,exercise_complete_id,exercise_item_id,errors_count',
            ])
            ->get()
            ->map(function (ExerciseComplete $completion): array {
                $wordsCount = $completion->exercise->items->count();
                $wordsWithErrors = $completion->itemResults
                    ->where('errors_count', '>', 0)
                    ->pluck('exercise_item_id')
                    ->unique()
                    ->count();
                $errorWords = $completion->itemResults
                    ->where('errors_count', '>', 0)
                    ->pluck('exercise_item_id')
                    ->unique()
                    ->flip();
                $words = $completion->exercise->items
                    ->sortBy('id')
                    ->map(fn ($item): array => [
                        'wordId' => $item->word->id,
                        'english' => $item->word->en,
                        'russian' => $item->word->ru,
                        'ruVariants' => $item->word->ru_variants ?? [],
                        'enVariants' => $item->word->en_variants ?? [],
                        'transcription' => $item->word->transcription,
                        'hasErrors' => $errorWords->has($item->id),
                    ])
                    ->values();
                $errorsCount = $completion->itemResults
                    ->sum('errors_count');
                $successPercentage = $wordsCount === 0
                    ? 0
                    : (int) round(
                        (($wordsCount - $wordsWithErrors) / $wordsCount) * 100,
                    );

                return [
                    'exerciseId' => $completion->exercise_id,
                    'createdAt' => $completion->exercise->created_at
                        ->toISOString(),
                    'completionId' => $completion->id,
                    'status' => 'completed',
                    'date' => $completion->completed_at->toISOString(),
                    'type' => [
                        'id' => $completion->exercise->type->id,
                        'name' => $completion->exercise->type->name,
                        'title' => $completion->exercise->type->title,
                    ],
                    'wordsCount' => $wordsCount,
                    'wordsWithErrors' => $wordsWithErrors,
                    'errorsCount' => $errorsCount,
                    'errorWords' => $words
                        ->where('hasErrors', true)
                        ->pluck('english')
                        ->values()
                        ->all(),
                    'words' => $words->all(),
                    'successPercentage' => $successPercentage,
                ];
            });

        return $uncompletedExercises
            ->concat($completions)
            ->sortBy('date')
            ->values();
    }

    /**
     * @param  Collection<int, User>  $users
     * @param  Collection<int, ExerciseComplete>  $completions
     * @return array{
     *     dateFrom: string,
     *     dateTo: string,
     *     users: Collection<int, array<string, int|string>>
     * }
     */
    private function chartPeriod(
        Collection $users,
        Collection $completions,
        CarbonImmutable $dateFrom,
        CarbonImmutable $dateTo,
    ): array {
        $completionsByUser = $completions->groupBy(
            fn (ExerciseComplete $completion): string => $completion
                ->exercise
                ->user_id,
        );

        $statistics = $users->map(function (User $user) use (
            $completionsByUser,
        ): array {
            /** @var Collection<int, ExerciseComplete> $userCompletions */
            $userCompletions = $completionsByUser->get(
                $user->id,
                collect(),
            );
            $resultsByWord = $userCompletions
                ->flatMap(
                    fn (ExerciseComplete $completion): Collection => $completion
                        ->itemResults,
                )
                ->groupBy(
                    fn ($result): int => (int) $result
                        ->exerciseItem
                        ->word_id,
                );
            $errorPercentages = $resultsByWord->map(
                fn (Collection $results): float => $this
                    ->errorPercentage($results),
            );

            return [
                'userId' => $user->id,
                'userName' => $user->name,
                'learnedWords' => $errorPercentages
                    ->filter(fn (float $percentage): bool => $percentage < 10)
                    ->count(),
                'wordsToRepeat' => $errorPercentages
                    ->filter(
                        fn (float $percentage): bool => $percentage >= 10
                            && $percentage <= 50,
                    )
                    ->count(),
                'completedExercises' => $userCompletions->count(),
            ];
        });

        return [
            'dateFrom' => $dateFrom->toISOString(),
            'dateTo' => $dateTo->toISOString(),
            'users' => $statistics,
        ];
    }

    private function errorPercentage(Collection $results): float
    {
        if ($results->isEmpty()) {
            return 0;
        }

        $resultsWithErrors = $results
            ->where('errors_count', '>', 0)
            ->count();

        return ($resultsWithErrors / $results->count()) * 100;
    }
}
