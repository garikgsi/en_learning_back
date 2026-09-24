<?php

namespace App\Enums;

enum GrammarRaceArticle: string
{
    case a = 'a';
    case an = 'an';
    case the = 'the';
    case none = 'none';

    public function label(): string
    {
        return match ($this) {
            self::none => 'без артикля',
            default => $this->value,
        };
    }
}
