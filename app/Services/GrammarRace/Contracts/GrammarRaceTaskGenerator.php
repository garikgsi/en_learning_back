<?php

namespace App\Services\GrammarRace\Contracts;

use App\Services\GrammarRace\Data\GeneratedGrammarRaceTask;

interface GrammarRaceTaskGenerator
{
    /**
     * @param  array<string, mixed>  $level
     * @return list<GeneratedGrammarRaceTask>
     */
    public function generate(array $level, int $count, float $reactionMultiplier = 1): array;
}
