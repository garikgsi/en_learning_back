<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\EnCoinService;
use Illuminate\Console\Command;

class AwardEnCoinWeeklyBonuses extends Command
{
    protected $signature = 'encoin:award-weekly-bonuses';

    protected $description = 'Award EnCoin bonuses for completed weeks';

    public function handle(EnCoinService $service): int
    {
        User::query()->orderBy('id')->chunk(100, function ($users) use ($service): void {
            foreach ($users as $user) {
                $service->awardCompletedWeeks($user);
            }
        });

        return self::SUCCESS;
    }
}
