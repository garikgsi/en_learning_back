<?php

namespace App\Enums;

enum GrammarRaceSessionStatus: string
{
    case active = 'active';
    case studentWon = 'student_won';
    case computerWon = 'computer_won';
    case abandoned = 'abandoned';
}
