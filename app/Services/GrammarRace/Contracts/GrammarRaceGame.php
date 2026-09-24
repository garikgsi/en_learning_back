<?php

namespace App\Services\GrammarRace\Contracts;

use App\Enums\GrammarRaceGameCode;

interface GrammarRaceGame
{
    public function code(): GrammarRaceGameCode;

    public function title(): string;

    public function rankTitle(): string;

    public function route(): string;

    public function minimumGrade(): int;

    /** @return list<string> */
    public function options(): array;

    /** @return array<int, array<string, mixed>> */
    public function levels(): array;

    public function generator(): GrammarRaceTaskGenerator;
}
