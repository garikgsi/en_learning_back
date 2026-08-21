<?php

return [
    'revision' => 4,
    'revision_released_at' => '2026-08-21T00:00:00Z',

    'drivers' => [
        'translation' => env(
            'DICTIONARY_TRANSLATION_DRIVER',
            'my_memory',
        ),
        'phonetics' => env(
            'DICTIONARY_PHONETICS_DRIVER',
            'merriam_webster_fallback',
        ),
        'speech' => env(
            'DICTIONARY_SPEECH_DRIVER',
            'merriam_webster_voice_rss',
        ),
    ],

    'audio' => [
        'disk' => env('DICTIONARY_AUDIO_DISK', 'dictionary_audio'),
    ],
];
