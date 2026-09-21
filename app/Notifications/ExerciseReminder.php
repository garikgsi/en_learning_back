<?php

namespace App\Notifications;

use App\Enums\ExerciseTypeCode;
use App\Models\Exercise;
use App\Notifications\Channels\NotificationJournalChannel;
use App\Notifications\Contracts\StoresInNotificationJournal;
use Illuminate\Notifications\Notification;

class ExerciseReminder extends Notification implements StoresInNotificationJournal
{
    public function __construct(
        private readonly Exercise $exercise,
    ) {}

    /**
     * @return list<class-string>
     */
    public function via(object $notifiable): array
    {
        return $this->isSupported() && ! $this->exercise->completions()->exists()
            ? [NotificationJournalChannel::class]
            : [];
    }

    public function deduplicationKey(object $notifiable): string
    {
        return "exercise:{$this->exercise->id}:reminder";
    }

    /**
     * @return array{type: string, title: string, body: string, data: array<string, scalar>}
     */
    public function toJournal(object $notifiable): array
    {
        return [
            'type' => 'exercise.reminder',
            'title' => 'Упражнение ждёт вас',
            'body' => "{$this->kind()} упражнение ещё не пройдено.",
            'data' => [
                'exerciseId' => $this->exercise->id,
                'route' => "/exercises/{$this->exercise->id}",
            ],
        ];
    }

    private function isSupported(): bool
    {
        return in_array((int) $this->exercise->type_id, [
            ExerciseTypeCode::daily->value,
            ExerciseTypeCode::weekly->value,
            ExerciseTypeCode::plural->value,
        ], true);
    }

    private function kind(): string
    {
        return (int) $this->exercise->type_id === ExerciseTypeCode::weekly->value
            ? 'Недельное'
            : 'Ежедневное';
    }
}
