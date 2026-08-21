<?php

namespace App\Services;

use App\Enums\ExerciseTypeCode;
use App\Exceptions\NoWordsAvailableException;
use App\Models\Exercise;
use App\Models\ExerciseItem;
use App\Models\ExerciseType;
use App\Models\User;
use App\Models\Word;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ExerciseService
{
    public function __construct(
        private readonly LeastRepeatedWordsService $leastRepeatedWordsService,
        private readonly ExerciseWordDeduplicator $wordDeduplicator,
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
                $excludedWordIds = (int) $type->id === ExerciseTypeCode::daily->value
                    ? $this->wordIdsFromRecentUncompletedExercises($user, $dueDate)
                    : [];

                $words = $this->leastRepeatedWordsService->get(
                    $user->id,
                    $wordsCount,
                    $excludedWordIds,
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

        if ($wordIds !== []) {
            $words = Word::query()
                ->findOrFail($wordIds)
                ->keyBy('id');
            $wordIds = array_map(
                fn (Word $word): int => $word->id,
                $this->wordDeduplicator->unique(array_map(
                    fn (int $wordId): Word => $words->get($wordId),
                    $wordIds,
                )),
            );
        }

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

    /**
     * @return array<int, int>
     */
    private function wordIdsFromRecentUncompletedExercises(
        User $user,
        CarbonInterface $dueDate,
    ): array {
        $dueDate = CarbonImmutable::instance($dueDate);

        return ExerciseItem::query()
            ->whereHas(
                'exercise',
                fn (Builder $query) => $query
                    ->where('user_id', $user->id)
                    ->where('dueDate', '>=', $dueDate->subMonth())
                    ->where('dueDate', '<', $dueDate)
                    ->whereDoesntHave('completions'),
            )
            ->distinct()
            ->pluck('word_id')
            ->map(fn ($wordId): int => (int) $wordId)
            ->all();
    }
}
