<?php

namespace App\Console\Commands;

use App\Enums\ExerciseTypeCode;
use App\Models\Exercise;
use App\Models\UserNotification;
use App\Notifications\ExerciseReminder;
use Illuminate\Console\Command;
use Symfony\Component\Console\Command\Command as SymfonyCommand;

class SendExerciseReminders extends Command
{
    protected $signature = 'exercises:send-reminders';

    protected $description = 'Notify users about uncompleted daily and weekly exercises';

    public function handle(): int
    {
        $remindedCount = 0;

        Exercise::query()
            ->whereIn('type_id', [
                ExerciseTypeCode::daily->value,
                ExerciseTypeCode::weekly->value,
                ExerciseTypeCode::plural->value,
            ])
            ->whereBetween('dueDate', [today(), today()->endOfDay()])
            ->whereDoesntHave('completions')
            ->whereHas('user', fn ($query) => $query->nonTest())
            ->orderBy('id')
            ->with('user')
            ->chunkById(100, function ($exercises) use (&$remindedCount): void {
                foreach ($exercises as $exercise) {
                    $notification = new ExerciseReminder($exercise);
                    $alreadyPublished = UserNotification::query()
                        ->where(
                            'deduplication_key',
                            $notification->deduplicationKey($exercise->user),
                        )->exists();

                    if ($alreadyPublished) {
                        continue;
                    }

                    $exercise->user->notify($notification);
                    $remindedCount++;
                }
            });

        $this->info("Exercise reminders created: {$remindedCount}.");

        return SymfonyCommand::SUCCESS;
    }
}
