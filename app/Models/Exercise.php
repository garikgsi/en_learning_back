<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read int $items_count
 */
#[Fillable(['user_id', 'type_id', 'dueDate'])]
class Exercise extends Model
{
    protected $table = 'exercise';

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<ExerciseType, $this>
     */
    public function type(): BelongsTo
    {
        return $this->belongsTo(ExerciseType::class, 'type_id');
    }

    /**
     * @return HasMany<ExerciseItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(ExerciseItem::class);
    }

    /**
     * @return HasMany<ExerciseComplete, $this>
     */
    public function completions(): HasMany
    {
        return $this->hasMany(ExerciseComplete::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'dueDate' => 'datetime',
        ];
    }
}
