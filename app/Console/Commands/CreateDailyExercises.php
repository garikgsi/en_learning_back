<?php

namespace App\Console\Commands;

use App\Enums\ExerciseTypeCode;
use App\Models\Exercise;
use App\Models\ExerciseType;
use App\Models\User;
use App\Services\ExerciseService;
use DomainException;
use Illuminate\Console\Command;
use Symfony\Component\Console\Command\Command as SymfonyCommand;

class CreateDailyExercises extends Command
{
    protected $signature = 'exercises:create-daily';

    protected $description = 'Create daily exercises for all users';

    public function handle(ExerciseService $exerciseService): int
    {
        $type = ExerciseType::forCode(ExerciseTypeCode::daily);
        $dueDate = today();
        $createdCount = 0;
        $skippedCount = 0;

        User::query()
            ->with('info')
            ->orderBy('id')
            ->chunk(100, function ($users) use (
                $dueDate,
                $exerciseService,
                $type,
                &$createdCount,
                &$skippedCount,
            ): void {
                foreach ($users as $user) {
                    $alreadyExists = Exercise::query()
                        ->where('user_id', $user->id)
                        ->where('type_id', $type->id)
                        ->where('dueDate', $dueDate)
                        ->exists();

                    if ($alreadyExists) {
                        $skippedCount++;

                        continue;
                    }

                    try {
                        $exerciseService->create(
                            $type,
                            $user,
                            $dueDate,
                        );
                        $createdCount++;
                    } catch (DomainException $exception) {
                        $this->warn(
                            "Skipped user {$user->id}: {$exception->getMessage()}",
                        );
                        $skippedCount++;
                    }
                }
            });

        $this->info(
            "Daily exercises created: {$createdCount}; skipped: {$skippedCount}.",
        );

        return SymfonyCommand::SUCCESS;
    }
}
