<?php

namespace App\Models;

use App\Enums\GrammarRaceGameCode;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'game_code',
    'current_level',
    'max_level',
    'games_played',
    'games_won',
    'games_at_level',
    'wins_at_level',
])]
class GrammarRaceProfile extends Model
{
    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'game_code' => GrammarRaceGameCode::class,
            'current_level' => 'integer',
            'max_level' => 'integer',
            'games_played' => 'integer',
            'games_won' => 'integer',
            'games_at_level' => 'integer',
            'wins_at_level' => 'integer',
        ];
    }
}
