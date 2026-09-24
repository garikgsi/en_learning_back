<?php

namespace App\Services\GrammarRace;

use App\Services\GrammarRace\Contracts\GrammarRaceTaskEvaluator;
use App\Services\GrammarRace\TaskTypes\SingleChoiceTaskEvaluator;
use InvalidArgumentException;

class GrammarRaceTaskEvaluatorRegistry
{
    /** @var list<GrammarRaceTaskEvaluator> */
    private array $evaluators;

    public function __construct(SingleChoiceTaskEvaluator $singleChoice)
    {
        $this->evaluators = [$singleChoice];
    }

    public function for(string $taskType): GrammarRaceTaskEvaluator
    {
        foreach ($this->evaluators as $evaluator) {
            if ($evaluator->supports($taskType)) {
                return $evaluator;
            }
        }

        throw new InvalidArgumentException("Unsupported grammar race task type {$taskType}.");
    }
}
