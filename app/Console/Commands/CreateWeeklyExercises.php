<?php

namespace App\Console\Commands;

use App\Enums\ExerciseTypeCode;
use App\Models\Exercise;
use App\Models\ExerciseType;
use App\Models\User;
use App\Notifications\ExerciseCreated;
use App\Services\ExerciseService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Console\Command\Command as SymfonyCommand;

class CreateWeeklyExercises extends Command
{
    protected $signature = 'exercises:create-weekly';

    protected $description = 'Create weekly exercises from Monday-to-Thursday daily exercises';

    public function handle(
        ExerciseService $exerciseService,
    ): int {
        $dailyType = ExerciseType::forCode(ExerciseTypeCode::daily);
        $weeklyType = ExerciseType::forCode(ExerciseTypeCode::weekly);
        $dueDate = today();
        $createdCount = 0;
        $skippedCount = 0;

        User::query()
            ->nonTest()
            ->orderBy('id')
            ->chunk(100, function ($users) use (
                $dailyType,
                $dueDate,
                $exerciseService,
                $weeklyType,
                &$createdCount,
                &$skippedCount,
            ): void {
                foreach ($users as $user) {
                    $alreadyExists = Exercise::query()
                        ->where('user_id', $user->id)
                        ->where('type_id', $weeklyType->id)
                        ->where('dueDate', $dueDate)
                        ->exists();

                    if ($alreadyExists) {
                        $skippedCount++;

                        continue;
                    }

                    $dailyExercises = Exercise::query()
                        ->where('user_id', $user->id)
                        ->where('type_id', $dailyType->id)
                        ->where('dueDate', '<', $dueDate)
                        ->whereHas('items')
                        ->with('items:id,exercise_id,word_id')
                        ->orderByDesc('created_at')
                        ->orderByDesc('id')
                        ->get();
                    $targetWordsCount = ((int) ($dailyExercises
                        ->first()?->items->count() ?? 0)) * 4;
                    $wordIds = [];

                    foreach ($dailyExercises as $dailyExercise) {
                        foreach ($dailyExercise->items as $item) {
                            $wordIds[(int) $item->word_id] = (int) $item->word_id;

                            if (count($wordIds) >= $targetWordsCount) {
                                break 2;
                            }
                        }
                    }

                    $wordIds = array_values($wordIds);

                    if ($wordIds === []) {
                        $skippedCount++;

                        continue;
                    }

                    DB::transaction(function () use (
                        $dueDate,
                        $exerciseService,
                        $user,
                        $weeklyType,
                        $wordIds,
                    ): void {
                        $exercise = $exerciseService->createWithWords(
                            $weeklyType,
                            $user,
                            $dueDate,
                            $wordIds,
                        );
                        $user->notify(new ExerciseCreated($exercise));
                    });
                    $createdCount++;
                }
            });

        $this->info(
            "Weekly exercises created: {$createdCount}; skipped: {$skippedCount}.",
        );

        return SymfonyCommand::SUCCESS;
    }
}
