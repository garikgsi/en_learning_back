<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['exercise_id', 'word_id'])]
class ExerciseItem extends Model
{
    /**
     * @return BelongsTo<Exercise, $this>
     */
    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class);
    }

    /**
     * @return BelongsTo<Word, $this>
     */
    public function word(): BelongsTo
    {
        return $this->belongsTo(Word::class);
    }
}
