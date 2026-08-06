<?php

namespace App\Services;

use App\Enums\ExerciseTypeCode;
use App\Exceptions\NoWordsAvailableException;
use App\Models\Exercise;
use App\Models\ExerciseType;
use App\Models\User;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ExerciseService
{
    public function __construct(
        private readonly LeastRepeatedWordsService $leastRepeatedWordsService,
    ) {}

    public function create(
        ExerciseType|ExerciseTypeCode $type,
        User $user,
        CarbonInterface $dueDate,
        int $wordsCount = 15,
    ): Exercise {
        if ($wordsCount < 1) {
            throw new InvalidArgumentException('Words count must be at least 1.');
        }

        if ($user->grade === null) {
            throw new DomainException('User info is required to create an exercise.');
        }

        if ($type instanceof ExerciseTypeCode) {
            $type = ExerciseType::forCode($type);
        }

        return DB::transaction(
            function () use (
                $dueDate,
                $type,
                $user,
                $wordsCount,
            ): Exercise {
                $words = $this->leastRepeatedWordsService->get(
                    $user->id,
                    $wordsCount,
                );

                return $this->createWithWords(
                    $type,
                    $user,
                    $dueDate,
                    array_map(fn ($word): int => $word->id, $words),
                );
            },
        );
    }

    /**
     * @param  array<int, int>  $wordIds
     */
    public function createWithWords(
        ExerciseType|ExerciseTypeCode $type,
        User $user,
        CarbonInterface $dueDate,
        array $wordIds,
    ): Exercise {
        if ($type instanceof ExerciseTypeCode) {
            $type = ExerciseType::forCode($type);
        }

        $wordIds = array_values(array_unique($wordIds));

        if ($wordIds === []) {
            throw new NoWordsAvailableException;
        }

        return DB::transaction(function () use ($type, $user, $dueDate, $wordIds): Exercise {
            $exercise = Exercise::query()->create([
                'user_id' => $user->id,
                'type_id' => $type->id,
                'dueDate' => $dueDate,
            ]);

            $exercise->items()->createMany(
                array_map(
                    fn (int $wordId): array => ['word_id' => $wordId],
                    $wordIds,
                ),
            );

            return $exercise->load(['type', 'items.word']);
        });
    }
}
