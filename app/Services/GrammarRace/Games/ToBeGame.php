<?php

namespace App\Services\GrammarRace\Games;

use App\Enums\GrammarRaceGameCode;
use App\Enums\ToBeForm;
use App\Services\GrammarRace\Contracts\GrammarRaceGame;
use App\Services\GrammarRace\Contracts\GrammarRaceTaskGenerator;
use App\Services\GrammarRace\ToBeTaskGenerator;

class ToBeGame implements GrammarRaceGame
{
    public function __construct(private readonly ToBeTaskGenerator $generator) {}

    public function code(): GrammarRaceGameCode
    {
        return GrammarRaceGameCode::toBe;
    }

    public function title(): string
    {
        return 'Форма глагола to be';
    }

    public function rankTitle(): string
    {
        return 'Знаток глагола to be';
    }

    public function route(): string
    {
        return '/games/to-be';
    }

    public function minimumGrade(): int
    {
        return (int) config('grammar_race.games.to_be.min_grade');
    }

    public function options(): array
    {
        return array_map(fn (ToBeForm $form): string => $form->value, ToBeForm::cases());
    }

    public function levels(): array
    {
        return config('grammar_race.games.to_be.levels', []);
    }

    public function generator(): GrammarRaceTaskGenerator
    {
        return $this->generator;
    }
}
