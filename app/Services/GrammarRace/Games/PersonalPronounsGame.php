<?php

namespace App\Services\GrammarRace\Games;

use App\Enums\GrammarRaceGameCode;
use App\Enums\PersonalPronoun;
use App\Services\GrammarRace\Contracts\GrammarRaceGame;
use App\Services\GrammarRace\Contracts\GrammarRaceTaskGenerator;
use App\Services\GrammarRace\PersonalPronounTaskGenerator;

class PersonalPronounsGame implements GrammarRaceGame
{
    public function __construct(private readonly PersonalPronounTaskGenerator $generator) {}

    public function code(): GrammarRaceGameCode
    {
        return GrammarRaceGameCode::personalPronouns;
    }

    public function title(): string
    {
        return 'Гонка местоимений';
    }

    public function rankTitle(): string
    {
        return 'Гонщик местоимений';
    }

    public function route(): string
    {
        return '/games/pronoun';
    }

    public function minimumGrade(): int
    {
        return (int) config('grammar_race.games.personal_pronouns.min_grade');
    }

    public function options(): array
    {
        return array_map(fn (PersonalPronoun $pronoun): string => $pronoun->value, PersonalPronoun::cases());
    }

    public function levels(): array
    {
        return config('grammar_race.games.personal_pronouns.levels', []);
    }

    public function generator(): GrammarRaceTaskGenerator
    {
        return $this->generator;
    }
}
