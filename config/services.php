<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'dictionary_translation' => [
        'url' => env(
            'DICTIONARY_TRANSLATION_URL',
            'https://api.mymemory.translated.net/get',
        ),
        'email' => env('DICTIONARY_TRANSLATION_EMAIL'),
    ],

    'dictionary_pronunciation' => [
        'url' => env(
            'DICTIONARY_PRONUNCIATION_URL',
            'https://api.dictionaryapi.dev/api/v2/entries/en',
        ),
    ],

    'merriam_webster' => [
        'url' => env(
            'MERRIAM_WEBSTER_URL',
            'https://www.dictionaryapi.com/api/v3/references',
        ),
        'reference' => env(
            'MERRIAM_WEBSTER_REFERENCE',
            'learners',
        ),
        'key' => env('MERRIAM_WEBSTER_API_KEY'),
        'audio_url' => env(
            'MERRIAM_WEBSTER_AUDIO_URL',
            'https://media.merriam-webster.com/audio/prons/en/us/mp3',
        ),
    ],

    'voice_rss' => [
        'url' => env('VOICE_RSS_URL', 'https://api.voicerss.org'),
        'key' => env('VOICE_RSS_API_KEY'),
        'language' => env('VOICE_RSS_LANGUAGE', 'en-us'),
        'voice' => env('VOICE_RSS_VOICE', 'Amy'),
        'audio_format' => env(
            'VOICE_RSS_AUDIO_FORMAT',
            '24khz_16bit_mono',
        ),
    ],

];
