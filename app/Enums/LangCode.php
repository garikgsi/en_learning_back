<?php

namespace App\Enums;

enum LangCode: int
{
    case en = 1;
    case ru = 2;

    public function title(): string
    {
        return match ($this) {
            self::en => 'Английский',
            self::ru => 'Русский',
        };
    }
}
