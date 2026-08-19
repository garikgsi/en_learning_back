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
            ->limit($wordsCount)
            ->lockForUpdate()
            ->get()
            ->pluck('word')
            ->all();

        $remainingWordsCount = $wordsCount - count($priorityWords);

        if ($remainingWordsCount === 0) {
            return $priorityWords;
        }

        $priorityWordIds = array_map(
            fn (Word $word): int => $word->id,
            $priorityWords,
        );

        $automaticWords = Word::query()
            ->where('grade', '<=', $user->grade)
            ->whereNotIn('id', [...$priorityWordIds, ...$excludedWordIds])
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
            ->limit($remainingWordsCount)
            ->get()
            ->all();

        return [...$priorityWords, ...$automaticWords];
    }
}
