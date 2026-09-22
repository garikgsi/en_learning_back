<?php

namespace App\Models;

use App\Enums\PersonalPronoun;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'grammar_race_session_id',
    'position',
    'prompt',
    'translation',
    'correct_answer',
    'bot_answer',
    'bot_delay_ms',
])]
class GrammarRaceTask extends Model
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
            'position' => 'integer',
            'correct_answer' => PersonalPronoun::class,
            'bot_answer' => PersonalPronoun::class,
            'bot_delay_ms' => 'integer',
        ];
    }
}
