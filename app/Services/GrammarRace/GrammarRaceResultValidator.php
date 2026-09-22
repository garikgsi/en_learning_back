<?php

namespace App\Services\GrammarRace;

use App\Enums\PersonalPronoun;
use App\Models\GrammarRaceSession;
use App\Models\GrammarRaceTask;
use Illuminate\Validation\ValidationException;

class GrammarRaceResultValidator
{
    /**
     * @param  list<array{taskPosition: int, playerAnswer: string|null, playerAnswerMs: int|null}>  $submittedRounds
     * @return array{rounds: list<array<string, int|string|null>>, studentScore: int, computerScore: int}
     */
    public function validate(GrammarRaceSession $session, array $submittedRounds): array
    {
        $tasks = $session->tasks->values();

        if ($tasks->isEmpty()) {
            throw ValidationException::withMessages(['rounds' => 'В игровой сессии отсутствуют задания.']);
        }

        $studentScore = 0;
        $computerScore = 0;
        $rounds = [];
        $winningScore = (int) config('grammar_race.winning_score');

        foreach ($submittedRounds as $index => $submitted) {
            if ($studentScore >= $winningScore || $computerScore >= $winningScore) {
                throw ValidationException::withMessages(['rounds' => 'После победного очка не должно быть дополнительных раундов.']);
            }

            /** @var GrammarRaceTask $task */
            $task = $tasks[$index % $tasks->count()];
            $expectedPosition = $task->position;

            if ($submitted['taskPosition'] !== $expectedPosition) {
                throw ValidationException::withMessages([
                    "rounds.{$index}.taskPosition" => "Ожидалось задание {$expectedPosition}.",
                ]);
            }

            $answer = $submitted['playerAnswer'];
            $answerMs = $submitted['playerAnswerMs'];

            if (($answer === null) !== ($answerMs === null)) {
                throw ValidationException::withMessages([
                    "rounds.{$index}.playerAnswer" => 'Ответ и время ответа должны быть переданы вместе.',
                ]);
            }

            $botCorrect = $task->bot_answer === $task->correct_answer;
            $roundEndMs = $botCorrect
                ? $task->bot_delay_ms
                : $task->bot_delay_ms + $session->answer_grace_ms;

            if ($answerMs !== null && $answerMs > $roundEndMs) {
                throw ValidationException::withMessages([
                    "rounds.{$index}.playerAnswerMs" => 'Ответ получен после завершения раунда.',
                ]);
            }

            $playerCorrect = $answer !== null
                && PersonalPronoun::from($answer) === $task->correct_answer;
            $outcome = 'no_score';

            if ($playerCorrect && $answerMs <= $task->bot_delay_ms) {
                $studentScore++;
                $outcome = 'student';
            } elseif ($botCorrect) {
                $computerScore++;
                $outcome = 'computer';
            } elseif ($playerCorrect) {
                $studentScore++;
                $outcome = 'student';
            }

            $rounds[] = [
                'sequence' => $index + 1,
                'task_position' => $task->position,
                'player_answer' => $answer,
                'player_answer_ms' => $answerMs,
                'outcome' => $outcome,
                'student_score' => $studentScore,
                'computer_score' => $computerScore,
            ];
        }

        if ($studentScore !== $winningScore && $computerScore !== $winningScore) {
            throw ValidationException::withMessages(['rounds' => 'Матч должен завершаться при достижении пяти очков.']);
        }

        return compact('rounds', 'studentScore', 'computerScore');
    }
}
