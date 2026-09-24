<?php

namespace App\Services;

use App\Enums\ExerciseTypeCode;
use App\Models\Exercise;
use App\Models\Plural;
use App\Models\User;
use Carbon\CarbonInterface;
use DomainException;
use InvalidArgumentException;

class PluralExerciseService
{
    public function __construct(
        private readonly ExerciseService $exerciseService,
    ) {}

    public function create(
        User $user,
        CarbonInterface $dueDate,
        int $pairsCount,
    ): Exercise {
        if ($pairsCount < 1) {
            throw new InvalidArgumentException('Pairs count must be at least 1.');
        }

        if ($user->grade === null) {
            throw new DomainException('User info is required to create an exercise.');
        }

        return $this->createForType($user, $dueDate, $pairsCount, ExerciseTypeCode::plural);
    }

    public function createUser(User $user, CarbonInterface $dueDate, int $pairsCount = 15): Exercise
    {
        return $this->createForType($user, $dueDate, $pairsCount, ExerciseTypeCode::userPlural);
    }

    private function createForType(
        User $user,
        CarbonInterface $dueDate,
        int $pairsCount,
        ExerciseTypeCode $type,
    ): Exercise {
        if ($pairsCount < 1) {
            throw new InvalidArgumentException('Pairs count must be at least 1.');
        }

        if ($user->grade === null) {
            throw new DomainException('User info is required to create an exercise.');
        }

        $wordIds = Plural::query()
            ->inRandomOrder()
            ->limit($pairsCount)
            ->pluck('word_id')
            ->map(fn ($wordId): int => (int) $wordId)
            ->all();

        return $this->exerciseService->createWithWords(
            $type,
            $user,
            $dueDate,
            $wordIds,
        );
    }
}
