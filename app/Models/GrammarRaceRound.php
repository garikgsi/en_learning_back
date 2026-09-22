<?php

namespace App\Models;

use App\Enums\PersonalPronoun;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'grammar_race_session_id',
    'sequence',
    'task_position',
    'player_answer',
    'player_answer_ms',
    'outcome',
    'student_score',
    'computer_score',
])]
class GrammarRaceRound extends Model
{
    /** @return BelongsTo<GrammarRaceSession, $this> */
    public function session(): BelongsTo
    {
        return $this->belongsTo(GrammarRaceSession::class, 'grammar_race_session_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'task_position' => 'integer',
            'player_answer' => PersonalPronoun::class,
            'player_answer_ms' => 'integer',
            'student_score' => 'integer',
            'computer_score' => 'integer',
        ];
    }
}
