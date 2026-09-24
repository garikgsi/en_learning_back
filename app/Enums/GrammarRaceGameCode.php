<?php

namespace App\Enums;

enum GrammarRaceGameCode: string
{
    case personalPronouns = 'personal_pronouns';
    case possessivePronouns = 'possessive_pronouns';
    case articles = 'articles';
    case toBe = 'to_be';
}
