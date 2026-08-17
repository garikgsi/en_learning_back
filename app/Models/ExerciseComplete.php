<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable([
    'exercise_id',
    'client_attempt_id',
    'request_hash',
    'completed_at',
])]
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

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'completed_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (ExerciseComplete $completion): void {
            $completion->client_attempt_id ??= (string) Str::uuid();
            $completion->request_hash ??= hash(
                'sha256',
                "internal:{$completion->client_attempt_id}",
            );
            $completion->completed_at ??= now();
        });
    }
}
