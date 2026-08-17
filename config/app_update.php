<?php

return [
    'github_repository' => env(
        'APP_UPDATE_GITHUB_REPOSITORY',
        'garikgsi/en_learning_tg_app',
    ),
    'channel' => env('APP_UPDATE_CHANNEL', 'prerelease'),
    'manifest_asset' => 'update-manifest.json',
    'cache_ttl_seconds' => 900,
    'http_timeout_seconds' => 5,
];
