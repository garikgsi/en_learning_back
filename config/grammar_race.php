<?php

use App\Enums\GrammarRaceTaskMode;

$phraseErrors = [25, 23, 21, 19, 17, 15, 13, 11, 8, 5];
$maxDelays = [10000, 9500, 9000, 8500, 8000, 7500, 7000, 6500, 6000, 5500];
$sentenceTimeMultiplier = 3;
$sentenceExtraTimeMs = 15000;
$winningScore = static fn (int $level, int $maxLevel): int => 5 + (int) round(
    ($level - 1) * 5 / max(1, $maxLevel - 1),
);
$personalPronounLevels = [];

foreach (range(1, 10) as $level) {
    $personalPronounLevels[$level] = [
        'mode' => GrammarRaceTaskMode::phrase->value,
        'bot_error_percent' => $phraseErrors[$level - 1],
        'bot_min_delay_ms' => 3000,
        'bot_max_delay_ms' => $maxDelays[$level - 1],
        'answer_grace_ms' => 5000,
        'show_translation' => $level === 1,
        'content_level' => $level,
        'winning_score' => $winningScore($level, 20),
    ];
}

foreach (range(11, 20) as $level) {
    $sentenceLevel = $level - 10;
    $personalPronounLevels[$level] = [
        'mode' => GrammarRaceTaskMode::sentence->value,
        'bot_error_percent' => 5,
        'bot_min_delay_ms' => 3000 * $sentenceTimeMultiplier + $sentenceExtraTimeMs,
        'bot_max_delay_ms' => $maxDelays[$sentenceLevel - 1] * $sentenceTimeMultiplier + $sentenceExtraTimeMs,
        'answer_grace_ms' => 10000,
        'show_translation' => false,
        'content_level' => $sentenceLevel,
        'winning_score' => $winningScore($level, 20),
    ];
}

$possessiveErrors = [25, 20, 15, 10, 5];
$possessiveMaxDelays = [10000, 8875, 7750, 6625, 5500];
$possessivePronounLevels = [];

foreach (range(1, 5) as $level) {
    $possessivePronounLevels[$level] = [
        'mode' => GrammarRaceTaskMode::sentence->value,
        'bot_error_percent' => $possessiveErrors[$level - 1],
        'bot_min_delay_ms' => 3000 * $sentenceTimeMultiplier + $sentenceExtraTimeMs,
        'bot_max_delay_ms' => $possessiveMaxDelays[$level - 1] * $sentenceTimeMultiplier + $sentenceExtraTimeMs,
        'answer_grace_ms' => 10000,
        'show_translation' => false,
        'content_level' => $level,
        'winning_score' => $winningScore($level, 5),
    ];
}

$articleErrors = [25, 23, 21, 19, 17, 15, 13, 11, 8, 5];
$articleMaxDelays = [10000, 9500, 9000, 8500, 8000, 7500, 7000, 6500, 6000, 5500];
$articleLevels = [];

foreach (range(1, 10) as $level) {
    $timeMultiplier = $level <= 5 ? 1 : $sentenceTimeMultiplier;
    $extraTimeMs = $level <= 5 ? 0 : $sentenceExtraTimeMs;
    $articleLevels[$level] = [
        'mode' => $level <= 5
            ? GrammarRaceTaskMode::phrase->value
            : GrammarRaceTaskMode::sentence->value,
        'bot_error_percent' => $articleErrors[$level - 1],
        'bot_min_delay_ms' => 3000 * $timeMultiplier + $extraTimeMs,
        'bot_max_delay_ms' => $articleMaxDelays[$level - 1] * $timeMultiplier + $extraTimeMs,
        'answer_grace_ms' => $level <= 5 ? 5000 : 10000,
        'show_translation' => $level === 1,
        'content_level' => $level,
        'winning_score' => $winningScore($level, 10),
    ];
}

$toBeErrors = [25, 23, 21, 19, 17, 15, 13, 11, 8, 5];
$toBeMaxDelays = [10000, 9500, 9000, 8500, 8000, 37500, 36000, 34500, 33000, 31500];
$toBeLevels = [];

foreach (range(1, 10) as $level) {
    $usesPastTense = $level >= 6;
    $toBeLevels[$level] = [
        'mode' => GrammarRaceTaskMode::sentence->value,
        'bot_error_percent' => $toBeErrors[$level - 1],
        'bot_min_delay_ms' => $usesPastTense ? 24000 : 3000,
        'bot_max_delay_ms' => $toBeMaxDelays[$level - 1],
        'answer_grace_ms' => $usesPastTense ? 10000 : 5000,
        'show_translation' => true,
        'content_level' => $level,
        'winning_score' => $winningScore($level, 10),
    ];
}

return [
    'games' => [
        'personal_pronouns' => [
            'min_grade' => 2,
            'levels' => $personalPronounLevels,
        ],
        'possessive_pronouns' => [
            'min_grade' => 5,
            'levels' => $possessivePronounLevels,
        ],
        'articles' => [
            'min_grade' => 3,
            'levels' => $articleLevels,
        ],
        'to_be' => [
            'min_grade' => 2,
            'levels' => $toBeLevels,
        ],
    ],
    'timezone' => 'Europe/Moscow',
    'daily_attempts' => 3,
    'free_attempts' => 1,
    'paid_attempt_cost' => 1,
    'win_reward' => 2,
    'winning_score' => 5,
    'task_pack_size' => 50,
    'rules_version' => '3',
    'generator_version' => '8',
    'level_up' => [
        'minimum_games' => 5,
        'minimum_win_rate' => 0.70,
    ],
];
