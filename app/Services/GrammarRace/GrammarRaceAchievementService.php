<?php

namespace App\Services\GrammarRace;

use App\Models\User;

class GrammarRaceAchievementService
{
    private const MEDAL_TIERS = 5;

    public function __construct(private readonly GrammarRaceGameRegistry $games) {}

    /** @return array{items: list<array<string, mixed>>, levelUp: array{minimumGames: int, minimumWinRatePercent: int}} */
    public function forUser(User $user): array
    {
        $profiles = $user->grammarRaceProfiles()->get()->keyBy(
            fn ($profile): string => $profile->game_code->value,
        );
        $minimumGames = (int) config('grammar_race.level_up.minimum_games');
        $minimumWinRate = (float) config('grammar_race.level_up.minimum_win_rate');

        return [
            'items' => array_map(function ($game) use ($user, $profiles, $minimumGames, $minimumWinRate): array {
                $profile = $profiles->get($game->code()->value);
                $currentLevel = $profile?->current_level ?? 1;
                $maxLevel = count($game->levels());
                $gamesAtLevel = $profile?->games_at_level ?? 0;
                $winsAtLevel = $profile?->wins_at_level ?? 0;
                $isMaxLevel = $currentLevel >= $maxLevel;

                return [
                    'gameCode' => $game->code()->value,
                    'gameTitle' => $game->title(),
                    'rankTitle' => $game->rankTitle(),
                    'route' => $game->route(),
                    'minGrade' => $game->minimumGrade(),
                    'isAvailable' => $user->grade !== null && $user->grade >= $game->minimumGrade(),
                    'currentLevel' => $currentLevel,
                    'maxLevel' => $maxLevel,
                    'medalTier' => $this->medalTier($currentLevel, $maxLevel),
                    'isMaxLevel' => $isMaxLevel,
                    'gamesPlayed' => $profile?->games_played ?? 0,
                    'gamesWon' => $profile?->games_won ?? 0,
                    'gamesAtLevel' => $gamesAtLevel,
                    'winsAtLevel' => $winsAtLevel,
                    'progressPercent' => $isMaxLevel
                        ? 100
                        : $this->progressPercent($gamesAtLevel, $winsAtLevel, $minimumGames, $minimumWinRate),
                ];
            }, $this->games->all()),
            'levelUp' => [
                'minimumGames' => $minimumGames,
                'minimumWinRatePercent' => (int) round($minimumWinRate * 100),
            ],
        ];
    }

    private function medalTier(int $currentLevel, int $maxLevel): int
    {
        if ($maxLevel <= 1) {
            return self::MEDAL_TIERS;
        }

        return min(
            self::MEDAL_TIERS,
            1 + (int) floor(
                ($currentLevel - 1) / ($maxLevel - 1) * self::MEDAL_TIERS,
            ),
        );
    }

    private function progressPercent(
        int $games,
        int $wins,
        int $minimumGames,
        float $minimumWinRate,
    ): int {
        if ($games === 0) {
            return 0;
        }

        $gamesProgress = min(1, $games / $minimumGames);
        $winRateProgress = min(1, ($wins / $games) / $minimumWinRate);

        return (int) round(min($gamesProgress, $winRateProgress) * 100);
    }
}
