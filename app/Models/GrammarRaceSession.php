<?php

namespace App\Models;

use App\Enums\GrammarRaceGameCode;
use App\Enums\GrammarRaceSessionStatus;
use App\Enums\GrammarRaceTaskMode;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id',
    'client_request_id',
    'game_code',
    'play_date',
    'attempt_number',
    'entry_cost',
    'reward',
    'difficulty_level',
    'task_mode',
    'bot_error_percent',
    'bot_min_delay_ms',
    'bot_max_delay_ms',
    'answer_grace_ms',
    'status',
    'student_score',
    'computer_score',
    'rules_version',
    'generator_version',
    'client_result_id',
    'result_hash',
    'started_at',
    'client_completed_at',
    'completed_at',
])]
class GrammarRaceSession extends Model
{
    use HasUuids;

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<GrammarRaceTask, $this> */
    public function tasks(): HasMany
    {
        return $this->hasMany(GrammarRaceTask::class)->orderBy('position');
    }

    /** @return HasMany<GrammarRaceRound, $this> */
    public function rounds(): HasMany
    {
        return $this->hasMany(GrammarRaceRound::class)->orderBy('sequence');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'game_code' => GrammarRaceGameCode::class,
            'task_mode' => GrammarRaceTaskMode::class,
            'status' => GrammarRaceSessionStatus::class,
            'play_date' => 'immutable_date',
            'started_at' => 'immutable_datetime',
            'client_completed_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'attempt_number' => 'integer',
            'entry_cost' => 'integer',
            'reward' => 'integer',
            'difficulty_level' => 'integer',
            'bot_error_percent' => 'integer',
            'bot_min_delay_ms' => 'integer',
            'bot_max_delay_ms' => 'integer',
            'answer_grace_ms' => 'integer',
            'student_score' => 'integer',
            'computer_score' => 'integer',
        ];
    }
}
