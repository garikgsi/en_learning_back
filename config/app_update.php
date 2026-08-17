<?php

return [
    'version_code' => (int) env('APP_UPDATE_VERSION_CODE', 0),
    'version_name' => env('APP_UPDATE_VERSION_NAME'),
    'apk_url' => env('APP_UPDATE_APK_URL'),
    'sha256' => env('APP_UPDATE_SHA256'),
    'size' => env('APP_UPDATE_SIZE'),
    'released_at' => env('APP_UPDATE_RELEASED_AT'),
    'release_notes' => env('APP_UPDATE_RELEASE_NOTES'),
    'mandatory' => (bool) env('APP_UPDATE_MANDATORY', false),
];
