<?php

namespace App\Services\GrammarRace;

use App\Enums\GrammarRaceGameCode;
use App\Models\GrammarRaceProfile;
use App\Models\User;
use App\Notifications\GrammarRaceLevelUp;

class GrammarRaceDifficultyService
{
    public function __construct(private readonly GrammarRaceGameRegistry $games) {}

    public function profile(User $user, GrammarRaceGameCode $gameCode): GrammarRaceProfile
    {
        return GrammarRaceProfile::query()->firstOrCreate(
            ['user_id' => $user->id, 'game_code' => $gameCode],
            ['current_level' => 1, 'max_level' => 1],
        );
    }

    public function recordResult(
        User $user,
        GrammarRaceGameCode $gameCode,
        bool $won,
    ): GrammarRaceProfile {
        $profile = GrammarRaceProfile::query()
            ->where('user_id', $user->id)
            ->where('game_code', $gameCode)
            ->lockForUpdate()
            ->first() ?? $this->profile($user, $gameCode);

        $gamesAtLevel = $profile->games_at_level + 1;
        $winsAtLevel = $profile->wins_at_level + ($won ? 1 : 0);
        $currentLevel = $profile->current_level;
        $previousLevel = $currentLevel;
        $minimumGames = (int) config('grammar_race.level_up.minimum_games');
        $minimumWinRate = (float) config('grammar_race.level_up.minimum_win_rate');

        if (
            $currentLevel < count($this->games->get($gameCode)->levels())
            && $gamesAtLevel >= $minimumGames
            && $winsAtLevel / $gamesAtLevel >= $minimumWinRate
        ) {
            $currentLevel++;
            $gamesAtLevel = 0;
            $winsAtLevel = 0;
        }

        $profile->update([
            'current_level' => $currentLevel,
            'max_level' => max($profile->max_level, $currentLevel),
            'games_played' => $profile->games_played + 1,
            'games_won' => $profile->games_won + ($won ? 1 : 0),
            'games_at_level' => $gamesAtLevel,
            'wins_at_level' => $winsAtLevel,
        ]);

        if ($currentLevel > $previousLevel) {
            $game = $this->games->get($gameCode);
            $user->notify(new GrammarRaceLevelUp(
                $gameCode,
                $game->title(),
                $game->rankTitle(),
                $currentLevel,
            ));
        }

        return $profile->refresh();
    }
}
