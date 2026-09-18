<?php

namespace App\Observers;

use App\Models\Word;
use App\Services\Dictionary\DictionaryRevisionService;
use Illuminate\Support\Facades\Schema;

class WordObserver
{
    public function updated(Word $word): void
    {
        if (! $word->wasChanged(['ru', 'en', 'ru_variants', 'en_variants', 'transcription', 'grade'])) {
            return;
        }

        // Earlier data migrations can update words before this table exists.
        if (! Schema::hasTable('dictionary_revisions')) {
            return;
        }

        app(DictionaryRevisionService::class)->bumpForGrades([
            (int) $word->getOriginal('grade'),
            $word->grade,
        ]);
    }
}
