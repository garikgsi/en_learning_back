<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'exercise_complete_id',
    'exercise_item_id',
    'errors_count',
    'hints_count',
    'lang_id',
    'variants',
])]
class ExerciseItemResult extends Model
{
    protected $table = 'exercise_items_result';

    /**
     * @return BelongsTo<ExerciseComplete, $this>
     */
    public function complete(): BelongsTo
    {
        return $this->belongsTo(
            ExerciseComplete::class,
            'exercise_complete_id',
        );
    }

    /**
     * @return BelongsTo<ExerciseItem, $this>
     */
    public function exerciseItem(): BelongsTo
    {
        return $this->belongsTo(ExerciseItem::class);
    }

    /**
     * @return BelongsTo<Lang, $this>
     */
    public function lang(): BelongsTo
    {
        return $this->belongsTo(Lang::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'errors_count' => 'integer',
            'hints_count' => 'integer',
            'variants' => 'array',
        ];
    }
}
