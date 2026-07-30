<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['exercise_id'])]
class ExerciseComplete extends Model
{
    protected $table = 'exercise_complete';

    /**
     * @return BelongsTo<Exercise, $this>
     */
    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class);
    }

    /**
     * @return HasMany<ExerciseItemResult, $this>
     */
    public function itemResults(): HasMany
    {
        return $this->hasMany(
            ExerciseItemResult::class,
            'exercise_complete_id',
        );
    }
}
