<?php

namespace App\Services;

use App\Enums\ExerciseTypeCode;
use App\Models\Exercise;
use App\Models\ExerciseType;
use App\Models\User;
use App\Models\Word;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ExerciseService
{
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

        return DB::transaction(function () use ($type, $user, $dueDate, $wordsCount): Exercise {
            $words = Word::query()
                ->where('grade', '<=', $user->grade)
                ->whereNot(fn($query) => $query->whereLike('words.ru', '% %')->orWhereLike('words.en', '% %'))
                ->inRandomOrder()
                ->limit($wordsCount)
                ->get(['id']);

            $exercise = Exercise::query()->create([
                'user_id' => $user->id,
                'type_id' => $type->id,
                'dueDate' => $dueDate,
            ]);

            $exercise->items()->createMany(
                $words->map(fn (Word $word): array => [
                    'word_id' => $word->id,
                ])->all(),
            );

            return $exercise->load(['type', 'items.word']);
        });
    }
}
