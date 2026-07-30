<?php

namespace App\Services;

use App\Models\Exercise;
use App\Models\ExerciseComplete;
use App\Models\UserWordRepetition;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExerciseCompletionService
{
    /**
     * @param  array<int, array{
     *     exercise_item_id: int,
     *     errors_count: int,
     *     hints_count: int,
     *     lang_id: int,
     *     variants: array<int, string>
     * }>  $itemResults
     */
    public function complete(
        Exercise $exercise,
        array $itemResults,
    ): ExerciseComplete {
        $exerciseItemIds = $exercise->items()
            ->pluck('id')
            ->map(fn ($id): int => (int) $id);

        foreach ($itemResults as $index => $itemResult) {
            if (! $exerciseItemIds->contains($itemResult['exercise_item_id'])) {
                throw ValidationException::withMessages([
                    "exercise_items_result.{$index}.exercise_item_id" => [
                        'The exercise item does not belong to the exercise.',
                    ],
                ]);
            }
        }

        return DB::transaction(
            function () use ($exercise, $itemResults): ExerciseComplete {
                $complete = $exercise->completions()->create();
                $complete->itemResults()->createMany($itemResults);

                UserWordRepetition::query()
                    ->where('user_id', $exercise->user_id)
                    ->where('is_active', true)
                    ->whereIn(
                        'word_id',
                        $exercise->items()->select('word_id'),
                    )
                    ->update(['is_active' => false]);

                return $complete->load(['itemResults.lang']);
            },
        );
    }
}
