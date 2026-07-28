<?php

namespace App\Support;

class RussianPhone
{
    public static function normalize(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $trimmed = trim($value);

        if (str_starts_with($trimmed, '+') && ! str_starts_with($trimmed, '+7')) {
            return $value;
        }

        $digits = preg_replace('/\D+/', '', $trimmed);

        if (! is_string($digits)) {
            return $value;
        }

        if (strlen($digits) === 10) {
            return '+7'.$digits;
        }

        if (strlen($digits) === 11 && in_array($digits[0], ['7', '8'], true)) {
            return '+7'.substr($digits, 1);
        }

        return $value;
    }
}
