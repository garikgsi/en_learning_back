<?php

namespace App\Enums;

enum ExerciseTypeCode: int
{
    case daily = 1;
    case weekly = 2;

    public function title(): string
    {
        return match ($this) {
            self::daily => 'Ежедневные',
            self::weekly => 'Недельные',
        };
    }
}
