<?php

namespace App\Console\Commands;

use App\Enums\ExerciseTypeCode;
use App\Models\Exercise;
use App\Services\Notifications\NotificationPublisher;
use Illuminate\Console\Command;
use Symfony\Component\Console\Command\Command as SymfonyCommand;

class SendExerciseReminders extends Command
{
    protected $signature = 'exercises:send-reminders';

    protected $description = 'Notify users about uncompleted daily and weekly exercises';

    public function handle(NotificationPublisher $notificationPublisher): int
    {
        $remindedCount = 0;

        Exercise::query()
            ->whereIn('type_id', [
                ExerciseTypeCode::daily->value,
                ExerciseTypeCode::weekly->value,
            ])
            ->whereBetween('dueDate', [today(), today()->endOfDay()])
            ->whereDoesntHave('completions')
            ->orderBy('id')
            ->chunkById(100, function ($exercises) use (
                $notificationPublisher,
                &$remindedCount,
            ): void {
                foreach ($exercises as $exercise) {
                    if ($notificationPublisher
                        ->exerciseReminder($exercise)
                        ?->wasRecentlyCreated) {
                        $remindedCount++;
                    }
                }
            });

        $this->info("Exercise reminders created: {$remindedCount}.");

        return SymfonyCommand::SUCCESS;
    }
}
