<?php

namespace App\Services\GrammarRace;

use App\Enums\GrammarRaceGameCode;
use App\Services\GrammarRace\Contracts\GrammarRaceGame;
use App\Services\GrammarRace\Games\ArticlesGame;
use App\Services\GrammarRace\Games\PersonalPronounsGame;
use App\Services\GrammarRace\Games\PossessivePronounsGame;

class GrammarRaceGameRegistry
{
    /** @var array<string, GrammarRaceGame> */
    private array $games;

    public function __construct(
        PersonalPronounsGame $personalPronouns,
        PossessivePronounsGame $possessivePronouns,
        ArticlesGame $articles,
    ) {
        $this->games = [
            $personalPronouns->code()->value => $personalPronouns,
            $possessivePronouns->code()->value => $possessivePronouns,
            $articles->code()->value => $articles,
        ];
    }

    public function get(GrammarRaceGameCode $code): GrammarRaceGame
    {
        return $this->games[$code->value];
    }

    /** @return list<GrammarRaceGame> */
    public function all(): array
    {
        return array_values($this->games);
    }
}
