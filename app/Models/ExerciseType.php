<?php

namespace App\Models;

use App\Enums\ExerciseTypeCode;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'title'])]
class ExerciseType extends Model
{
    protected $table = 'exercise_type';

    public static function forCode(ExerciseTypeCode $code): self
    {
        return self::query()->findOrFail($code->value);
    }

    /**
     * @return HasMany<Exercise, $this>
     */
    public function exercises(): HasMany
    {
        return $this->hasMany(Exercise::class, 'type_id');
    }
}
