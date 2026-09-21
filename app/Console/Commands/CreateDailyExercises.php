<?php

namespace App\Console\Commands;

use App\Enums\ExerciseTypeCode;
use App\Models\Exercise;
use App\Models\ExerciseType;
use App\Models\User;
use App\Notifications\ExerciseCreated;
use App\Services\ExerciseService;
use App\Services\PluralExerciseService;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Console\Command\Command as SymfonyCommand;

class CreateDailyExercises extends Command
{
    private const int PRIMARY_SCHOOL_WORDS_COUNT = 10;

    private const int DEFAULT_WORDS_COUNT = 15;

    protected $signature = 'exercises:create-daily';

    protected $description = 'Create daily exercises for all users';

    public function handle(
        ExerciseService $exerciseService,
        PluralExerciseService $pluralExerciseService,
    ): int {
        $dueDate = today();
        $typeCode = $dueDate->dayOfWeekIso === CarbonInterface::THURSDAY
            ? ExerciseTypeCode::plural
            : ExerciseTypeCode::daily;
        $type = ExerciseType::forCode($typeCode);
        $createdCount = 0;
        $skippedCount = 0;

        User::query()
            ->nonTest()
            ->with('info')
            ->orderBy('id')
            ->chunk(100, function ($users) use (
                $dueDate,
                $exerciseService,
                $pluralExerciseService,
                $type,
                $typeCode,
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
                        DB::transaction(function () use (
                            $dueDate,
                            $exerciseService,
                            $pluralExerciseService,
                            $type,
                            $typeCode,
                            $user,
                        ): void {
                            $wordsCount = $user->grade <= 5
                                ? self::PRIMARY_SCHOOL_WORDS_COUNT
                                : self::DEFAULT_WORDS_COUNT;
                            $exercise = $typeCode === ExerciseTypeCode::plural
                                ? $pluralExerciseService->create(
                                    $user,
                                    $dueDate,
                                    $wordsCount,
                                )
                                : $exerciseService->create(
                                    $type,
                                    $user,
                                    $dueDate,
                                    $wordsCount,
                                );
                            $user->notify(new ExerciseCreated($exercise));
                        });
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
