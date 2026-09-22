<?php

use App\Enums\GrammarRaceTaskMode;

$phraseErrors = [25, 23, 21, 19, 17, 15, 13, 11, 8, 5];
$maxDelays = [10000, 9500, 9000, 8500, 8000, 7500, 7000, 6500, 6000, 5500];
$levels = [];

foreach (range(1, 10) as $level) {
    $levels[$level] = [
        'mode' => GrammarRaceTaskMode::phrase->value,
        'bot_error_percent' => $phraseErrors[$level - 1],
        'bot_min_delay_ms' => 3000,
        'bot_max_delay_ms' => $maxDelays[$level - 1],
        'answer_grace_ms' => 5000,
        'show_translation' => $level === 1,
        'content_level' => $level,
    ];
}

foreach (range(11, 20) as $level) {
    $sentenceLevel = $level - 10;
    $levels[$level] = [
        'mode' => GrammarRaceTaskMode::sentence->value,
        'bot_error_percent' => 5,
        'bot_min_delay_ms' => 3000,
        'bot_max_delay_ms' => $maxDelays[$sentenceLevel - 1],
        'answer_grace_ms' => 10000,
        'show_translation' => false,
        'content_level' => $sentenceLevel,
    ];
}

return [
    'timezone' => 'Europe/Moscow',
    'daily_attempts' => 3,
    'free_attempts' => 1,
    'paid_attempt_cost' => 1,
    'win_reward' => 2,
    'winning_score' => 5,
    'task_pack_size' => 50,
    'rules_version' => '1',
    'generator_version' => '1',
    'level_up' => [
        'minimum_games' => 5,
        'minimum_win_rate' => 0.70,
    ],
    'levels' => $levels,
];
