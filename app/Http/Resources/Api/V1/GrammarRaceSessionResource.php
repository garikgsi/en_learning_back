<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GrammarRaceSessionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'gameCode' => $this->game_code->value,
            'status' => $this->status->value,
            'attemptNumber' => $this->attempt_number,
            'entryCost' => $this->entry_cost,
            'winReward' => $this->reward,
            'difficulty' => [
                'level' => $this->difficulty_level,
                'mode' => $this->task_mode->value,
                'botErrorPercent' => $this->bot_error_percent,
                'botMinDelayMs' => $this->bot_min_delay_ms,
                'botMaxDelayMs' => $this->bot_max_delay_ms,
                'answerGraceMs' => $this->answer_grace_ms,
            ],
            'winningScore' => (int) config('grammar_race.winning_score'),
            'options' => ['he', 'she', 'it', 'we', 'they'],
            'score' => [
                'student' => $this->student_score,
                'computer' => $this->computer_score,
            ],
            'tasks' => $this->whenLoaded('tasks', fn () => $this->tasks->map(fn ($task): array => [
                'position' => $task->position,
                'prompt' => $task->prompt,
                'translation' => $task->translation,
                'correctAnswer' => $task->correct_answer->value,
                'botAnswer' => $task->bot_answer->value,
                'botDelayMs' => $task->bot_delay_ms,
            ])->values()),
            'rounds' => $this->whenLoaded('rounds', fn () => $this->rounds->map(fn ($round): array => [
                'sequence' => $round->sequence,
                'taskPosition' => $round->task_position,
                'playerAnswer' => $round->player_answer?->value,
                'playerAnswerMs' => $round->player_answer_ms,
                'outcome' => $round->outcome,
                'studentScore' => $round->student_score,
                'computerScore' => $round->computer_score,
            ])->values()),
            'startedAt' => $this->started_at,
            'clientCompletedAt' => $this->client_completed_at,
            'completedAt' => $this->completed_at,
        ];
    }
}
