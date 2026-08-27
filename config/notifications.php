<?php

return [
    'delivery_window' => [
        'timezone' => 'Europe/Moscow',
        'starts_at' => '12:00',
        'ends_at' => '20:00',
    ],

    'push' => [
        'enabled' => (bool) env('PUSH_NOTIFICATIONS_ENABLED', false),
    ],

    'firebase' => [
        'project_id' => env('FIREBASE_PROJECT_ID'),
        'credentials' => env(
            'GOOGLE_APPLICATION_CREDENTIALS',
            '/run/secrets/firebase-service-account.json',
        ),
        'api_url' => env(
            'FIREBASE_API_URL',
            'https://fcm.googleapis.com/v1',
        ),
        'android_channel_id' => env(
            'FIREBASE_ANDROID_CHANNEL_ID',
            'exercises',
        ),
    ],
];
