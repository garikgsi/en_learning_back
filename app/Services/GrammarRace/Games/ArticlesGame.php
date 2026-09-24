<?php

namespace App\Services\GrammarRace\Games;

use App\Enums\GrammarRaceArticle;
use App\Enums\GrammarRaceGameCode;
use App\Services\GrammarRace\ArticleTaskGenerator;
use App\Services\GrammarRace\Contracts\GrammarRaceGame;
use App\Services\GrammarRace\Contracts\GrammarRaceTaskGenerator;

class ArticlesGame implements GrammarRaceGame
{
    public function __construct(private readonly ArticleTaskGenerator $generator) {}

    public function code(): GrammarRaceGameCode
    {
        return GrammarRaceGameCode::articles;
    }

    public function title(): string
    {
        return 'Гонка артиклей';
    }

    public function rankTitle(): string
    {
        return 'Знаток артиклей';
    }

    public function route(): string
    {
        return '/games/articles';
    }

    public function minimumGrade(): int
    {
        return (int) config('grammar_race.games.articles.min_grade');
    }

    public function options(): array
    {
        return array_map(fn (GrammarRaceArticle $article): string => $article->value, GrammarRaceArticle::cases());
    }

    public function levels(): array
    {
        return config('grammar_race.games.articles.levels', []);
    }

    public function generator(): GrammarRaceTaskGenerator
    {
        return $this->generator;
    }
}
