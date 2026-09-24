<?php

namespace App\Services\GrammarRace\TaskTypes;

use App\Models\GrammarRaceTask;
use App\Services\GrammarRace\Contracts\GrammarRaceTaskEvaluator;

class SingleChoiceTaskEvaluator implements GrammarRaceTaskEvaluator
{
    public function supports(string $taskType): bool
    {
        return $taskType === 'single_choice';
    }

    public function isCorrect(GrammarRaceTask $task, ?string $answer): bool
    {
        return $answer !== null && hash_equals($task->correct_answer, $answer);
    }
}
