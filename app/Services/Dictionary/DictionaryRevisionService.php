<?php

namespace App\Services\Dictionary;

use Illuminate\Support\Facades\DB;

class DictionaryRevisionService
{
    public function forGrade(?int $availableGrade): int
    {
        return (int) config('dictionary.revision') + (int) DB::table('dictionary_revisions')
            ->when($availableGrade !== null, fn ($query) => $query->where('grade', '<=', $availableGrade))
            ->sum('revision');
    }

    /** @param list<int> $grades */
    public function bumpForGrades(array $grades): void
    {
        $grades = array_values(array_unique($grades));
        sort($grades);

        DB::transaction(function () use ($grades): void {
            foreach ($grades as $grade) {
                DB::table('dictionary_revisions')->insertOrIgnore(['grade' => $grade, 'revision' => 0]);
                DB::table('dictionary_revisions')->where('grade', $grade)->increment('revision');
            }
        });
    }
}
