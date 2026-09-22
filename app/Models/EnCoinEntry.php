<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['user_id', 'amount', 'kopecks_per_coin', 'reason', 'source_key', 'exercise_id', 'grammar_race_session_id'])]
class EnCoinEntry extends Model
{
    protected $table = 'encoin_entries';

    protected function casts(): array
    {
        return ['amount' => 'integer'];
    }
}
