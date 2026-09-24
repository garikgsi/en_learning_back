<?php

namespace App\Services\GrammarRace\Contracts;

use App\Models\GrammarRaceTask;

interface GrammarRaceTaskEvaluator
{
    public function supports(string $taskType): bool;

    public function isCorrect(GrammarRaceTask $task, ?string $answer): bool;
}
