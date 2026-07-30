<?php

namespace App\Services;

use App\Models\Exercise;
use App\Models\ExerciseComplete;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class ExerciseStatisticsService
{
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
            ->with('type')
            ->withCount('items')
            ->get()
            ->map(fn (Exercise $exercise): array => [
                'exerciseId' => $exercise->id,
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
                'successPercentage' => 0,
            ]);

        $completions = ExerciseComplete::query()
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->whereHas(
                'exercise',
                fn ($query) => $query->where('user_id', $user->id),
            )
            ->with([
                'exercise.type',
                'exercise.items:id,exercise_id',
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
                $successPercentage = $wordsCount === 0
                    ? 0
                    : (int) round(
                        (($wordsCount - $wordsWithErrors) / $wordsCount) * 100,
                    );

                return [
                    'exerciseId' => $completion->exercise_id,
                    'completionId' => $completion->id,
                    'status' => 'completed',
                    'date' => $completion->created_at->toISOString(),
                    'type' => [
                        'id' => $completion->exercise->type->id,
                        'name' => $completion->exercise->type->name,
                        'title' => $completion->exercise->type->title,
                    ],
                    'wordsCount' => $wordsCount,
                    'wordsWithErrors' => $wordsWithErrors,
                    'successPercentage' => $successPercentage,
                ];
            });

        return $uncompletedExercises
            ->concat($completions)
            ->sortBy('date')
            ->values();
    }
}
