<?php

namespace App\Models;

use App\Enums\LangCode;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'title'])]
class Lang extends Model
{
    protected $table = 'lang';

    public static function forCode(LangCode $code): self
    {
        return self::query()->findOrFail($code->value);
    }

    /**
     * @return HasMany<ExerciseItemResult, $this>
     */
    public function exerciseItemResults(): HasMany
    {
        return $this->hasMany(ExerciseItemResult::class);
    }
}
