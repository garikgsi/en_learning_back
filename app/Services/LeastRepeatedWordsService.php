<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserWordRepetition;
use App\Models\Word;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

class LeastRepeatedWordsService
{
    public function __construct(
        private readonly ExerciseWordDeduplicator $wordDeduplicator,
    ) {}

    /**
     * @param  array<int, int>  $excludedWordIds
     * @return array<int, Word>
     */
    public function get(
        string $userId,
        int $wordsCount,
        array $excludedWordIds = [],
    ): array {
        if ($wordsCount < 1) {
            throw new InvalidArgumentException(
                'Words count must be at least 1.',
            );
        }

        $user = User::query()
            ->with('info')
            ->findOrFail($userId);

        if ($user->grade === null) {
            throw new DomainException(
                'User info is required to select words.',
            );
        }

        $priorityWords = UserWordRepetition::query()
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->whereNotIn('word_id', $excludedWordIds)
            ->with('word')
            ->inRandomOrder()
            ->lockForUpdate()
            ->get()
            ->pluck('word')
            ->all();

        $automaticWords = Word::query()
            ->where('grade', '<=', $user->grade)
            ->whereNotIn('id', $excludedWordIds)
            ->whereNot(
                fn (Builder $query) => $query
                    ->whereLike('words.ru', '% %')
                    ->orWhereLike('words.en', '% %'),
            )
            ->withCount([
                'exerciseItemResults as repeats_count' => fn (Builder $query) => $query
                    ->whereHas(
                        'complete.exercise',
                        fn (Builder $query) => $query
                            ->where('user_id', $userId),
                    ),
            ])
            ->orderBy('repeats_count')
            ->inRandomOrder()
            ->get()
            ->all();

        return $this->wordDeduplicator->unique(
            [...$priorityWords, ...$automaticWords],
            $wordsCount,
        );
    }
}
