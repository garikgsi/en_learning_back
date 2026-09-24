<?php

namespace App\Services\GrammarRace;

use App\Models\GrammarRaceSession;
use App\Models\GrammarRaceTask;
use Illuminate\Validation\ValidationException;

class GrammarRaceResultValidator
{
    private const RETRY_BOT_DELAY_MS = 500;

    public function __construct(private readonly GrammarRaceTaskEvaluatorRegistry $evaluators) {}

    /**
     * @param  list<array{taskPosition: int, playerAnswer: string|null, playerAnswerMs: int|null, secondPlayerAnswer?: string|null, secondPlayerAnswerMs?: int|null}>  $submittedRounds
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
            $secondAnswer = $submitted['secondPlayerAnswer'] ?? null;
            $secondAnswerMs = $submitted['secondPlayerAnswerMs'] ?? null;

            if (($answer === null) !== ($answerMs === null)) {
                throw ValidationException::withMessages([
                    "rounds.{$index}.playerAnswer" => 'Ответ и время ответа должны быть переданы вместе.',
                ]);
            }
            if (($secondAnswer === null) !== ($secondAnswerMs === null)) {
                throw ValidationException::withMessages([
                    "rounds.{$index}.secondPlayerAnswer" => 'Второй ответ и время второго ответа должны быть переданы вместе.',
                ]);
            }

            $optionIds = collect($task->options ?? [])->pluck('id')->all();
            if ($answer !== null && $optionIds !== [] && ! in_array($answer, $optionIds, true)) {
                throw ValidationException::withMessages([
                    "rounds.{$index}.playerAnswer" => 'Ответ отсутствует среди вариантов задания.',
                ]);
            }
            if ($secondAnswer !== null && $optionIds !== [] && ! in_array($secondAnswer, $optionIds, true)) {
                throw ValidationException::withMessages([
                    "rounds.{$index}.secondPlayerAnswer" => 'Второй ответ отсутствует среди вариантов задания.',
                ]);
            }

            $evaluator = $this->evaluators->for($task->task_type ?? 'single_choice');
            $botCorrect = $evaluator->isCorrect($task, $task->bot_answer);
            $playerCorrect = $evaluator->isCorrect($task, $answer);
            $earlyMistake = $answer !== null
                && $answerMs !== null
                && ! $playerCorrect
                && $answerMs < $task->bot_delay_ms;
            $roundEndMs = $botCorrect
                ? $task->bot_delay_ms
                : $task->bot_delay_ms + $session->answer_grace_ms;

            if ($answerMs !== null && $answerMs > $roundEndMs) {
                throw ValidationException::withMessages([
                    "rounds.{$index}.playerAnswerMs" => 'Ответ получен после завершения раунда.',
                ]);
            }

            if ($secondAnswer !== null) {
                if (! $earlyMistake || $botCorrect) {
                    throw ValidationException::withMessages([
                        "rounds.{$index}.secondPlayerAnswer" => 'Вторая попытка недоступна для этого раунда.',
                    ]);
                }

                $secondAttemptStartsAt = $answerMs + self::RETRY_BOT_DELAY_MS;
                $secondAttemptEndsAt = $task->bot_delay_ms + $session->answer_grace_ms;
                if ($secondAnswerMs < $secondAttemptStartsAt || $secondAnswerMs > $secondAttemptEndsAt) {
                    throw ValidationException::withMessages([
                        "rounds.{$index}.secondPlayerAnswerMs" => 'Второй ответ получен вне времени второй попытки.',
                    ]);
                }
            }

            $secondPlayerCorrect = $evaluator->isCorrect($task, $secondAnswer);
            $outcome = 'no_score';

            if ($playerCorrect && $answerMs <= $task->bot_delay_ms) {
                $studentScore++;
                $outcome = 'student';
            } elseif ($botCorrect) {
                $computerScore++;
                $outcome = 'computer';
            } elseif ($earlyMistake && $secondPlayerCorrect) {
                $studentScore++;
                $outcome = 'student';
            } elseif (! $earlyMistake && $playerCorrect) {
                $studentScore++;
                $outcome = 'student';
            }

            $rounds[] = [
                'sequence' => $index + 1,
                'task_position' => $task->position,
                'player_answer' => $answer,
                'player_answer_ms' => $answerMs,
                'second_player_answer' => $secondAnswer,
                'second_player_answer_ms' => $secondAnswerMs,
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
