<?php

namespace App\Services\Dictionary\Enums;

enum AudioFormat: string
{
    case Mp3 = 'mp3';
    case Ogg = 'ogg';
    case Wav = 'wav';

    public function contentType(): string
    {
        return match ($this) {
            self::Mp3 => 'audio/mpeg',
            self::Ogg => 'audio/ogg',
            self::Wav => 'audio/wav',
        };
    }
}
