<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/**
 * @property-read bool $is_active
 * @property-read int $repeat_count
 * @property-read int $successful_repeat_count
 * @property-read int $failed_repeat_count
 */
#[Fillable([
    'ru',
    'en',
    'ru_variants',
    'en_variants',
    'transcription',
    'grade',
])]
class Word extends Model
{
    /**
     * @return HasMany<ExerciseItem, $this>
     */
    public function exerciseItems(): HasMany
    {
        return $this->hasMany(ExerciseItem::class);
    }

    /**
     * @return HasManyThrough<ExerciseItemResult, ExerciseItem, $this>
     */
    public function exerciseItemResults(): HasManyThrough
    {
        return $this->hasManyThrough(
            ExerciseItemResult::class,
            ExerciseItem::class,
            'word_id',
            'exercise_item_id',
        );
    }

    /**
     * @return HasMany<UserWordRepetition, $this>
     */
    public function userRepetitions(): HasMany
    {
        return $this->hasMany(UserWordRepetition::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'grade' => 'integer',
            'ru_variants' => 'array',
            'en_variants' => 'array',
        ];
    }
}
