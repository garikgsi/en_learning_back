<?php

namespace App\Console\Commands;

use App\Enums\ExerciseTypeCode;
use App\Models\Exercise;
use App\Models\ExerciseItem;
use App\Models\ExerciseType;
use App\Models\User;
use App\Services\ExerciseService;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;
use Symfony\Component\Console\Command\Command as SymfonyCommand;

class CreateWeeklyExercises extends Command
{
    protected $signature = 'exercises:create-weekly';

    protected $description = 'Create weekly exercises from Monday-to-Thursday daily exercises';

    public function handle(ExerciseService $exerciseService): int
    {
        $dailyType = ExerciseType::forCode(ExerciseTypeCode::daily);
        $weeklyType = ExerciseType::forCode(ExerciseTypeCode::weekly);
        $dueDate = today();
        $periodStart = $dueDate->copy()->startOfWeek(CarbonInterface::MONDAY);
        $periodEnd = $periodStart->copy()->addDays(3)->endOfDay();
        $createdCount = 0;
        $skippedCount = 0;

        User::query()
            ->orderBy('id')
            ->chunk(100, function ($users) use (
                $dailyType,
                $dueDate,
                $exerciseService,
                $periodEnd,
                $periodStart,
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

                    $wordIds = ExerciseItem::query()
                        ->whereHas(
                            'exercise',
                            fn ($query) => $query
                                ->where('user_id', $user->id)
                                ->where('type_id', $dailyType->id)
                                ->whereBetween('dueDate', [
                                    $periodStart,
                                    $periodEnd,
                                ]),
                        )
                        ->distinct()
                        ->pluck('word_id')
                        ->map(fn ($wordId): int => (int) $wordId)
                        ->all();

                    if ($wordIds === []) {
                        $skippedCount++;

                        continue;
                    }

                    $exerciseService->createWithWords(
                        $weeklyType,
                        $user,
                        $dueDate,
                        $wordIds,
                    );
                    $createdCount++;
                }
            });

        $this->info(
            "Weekly exercises created: {$createdCount}; skipped: {$skippedCount}.",
        );

        return SymfonyCommand::SUCCESS;
    }
}
