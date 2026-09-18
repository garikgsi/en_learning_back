<?php

namespace App\Services;

use App\Enums\ExerciseTypeCode;
use App\Models\Exercise;
use App\Models\ExerciseType;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class AdminDailyExerciseService
{
    /**
     * @param  list<int>  $wordIds
     * @return array{exercise: Exercise, replaced: bool}
     */
    public function assign(User $user, array $wordIds, CarbonInterface $dueDate, bool $replaceExisting): array
    {
        $dueDate = CarbonImmutable::instance($dueDate)->startOfDay();

        return DB::transaction(function () use ($user, $wordIds, $dueDate, $replaceExisting): array {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $exercise = $replaceExisting
                ? $user->exercises()
                    ->where('type_id', ExerciseTypeCode::daily->value)
                    ->whereBetween('dueDate', [$dueDate, $dueDate->endOfDay()])
                    ->whereDoesntHave('completions')
                    ->orderByDesc('id')
                    ->lockForUpdate()
                    ->first()
                : null;

            // Completion uses the same exercise lock. Recheck after acquiring it.
            if ($exercise !== null && $exercise->completions()->exists()) {
                $exercise = null;
            }
            $replaced = $exercise !== null;
            if ($replaced) {
                $exercise->items()->delete();
                $exercise->touch();
            } else {
                $exercise = $user->exercises()->create([
                    'type_id' => ExerciseType::forCode(ExerciseTypeCode::daily)->id,
                    'dueDate' => $dueDate,
                ]);
            }

            // Manual selections are exact: phrases and any grades are allowed.
            $exercise->items()->createMany(array_map(
                fn (int $wordId): array => ['word_id' => $wordId],
                $wordIds,
            ));

            return ['exercise' => $exercise->load(['type', 'items.word']), 'replaced' => $replaced];
        });
    }
}
