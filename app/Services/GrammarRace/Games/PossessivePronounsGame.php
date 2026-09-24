<?php

namespace App\Services\GrammarRace\Games;

use App\Enums\GrammarRaceGameCode;
use App\Enums\PossessivePronoun;
use App\Services\GrammarRace\Contracts\GrammarRaceGame;
use App\Services\GrammarRace\Contracts\GrammarRaceTaskGenerator;
use App\Services\GrammarRace\PossessivePronounTaskGenerator;

class PossessivePronounsGame implements GrammarRaceGame
{
    public function __construct(private readonly PossessivePronounTaskGenerator $generator) {}

    public function code(): GrammarRaceGameCode
    {
        return GrammarRaceGameCode::possessivePronouns;
    }

    public function title(): string
    {
        return 'Притяжательные местоимения';
    }

    public function rankTitle(): string
    {
        return 'Знаток притяжательных местоимений';
    }

    public function route(): string
    {
        return '/games/possessive-pronoun';
    }

    public function minimumGrade(): int
    {
        return (int) config('grammar_race.games.possessive_pronouns.min_grade');
    }

    public function options(): array
    {
        return array_map(fn (PossessivePronoun $pronoun): string => $pronoun->value, PossessivePronoun::cases());
    }

    public function levels(): array
    {
        return config('grammar_race.games.possessive_pronouns.levels', []);
    }

    public function generator(): GrammarRaceTaskGenerator
    {
        return $this->generator;
    }
}
