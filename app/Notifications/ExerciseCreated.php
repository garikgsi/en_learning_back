<?php

namespace App\Notifications;

use App\Enums\ExerciseTypeCode;
use App\Models\Exercise;
use App\Notifications\Channels\NotificationJournalChannel;
use App\Notifications\Contracts\StoresInNotificationJournal;
use Illuminate\Notifications\Notification;

class ExerciseCreated extends Notification implements StoresInNotificationJournal
{
    public function __construct(
        private readonly Exercise $exercise,
    ) {}

    /**
     * @return list<class-string>
     */
    public function via(object $notifiable): array
    {
        return $this->isSupported()
            ? [NotificationJournalChannel::class]
            : [];
    }

    public function deduplicationKey(object $notifiable): string
    {
        return "exercise:{$this->exercise->id}:created";
    }

    /**
     * @return array{type: string, title: string, body: string, data: array<string, scalar>}
     */
    public function toJournal(object $notifiable): array
    {
        $this->exercise->loadCount('items');

        return [
            'type' => 'exercise.created',
            'title' => 'Новое упражнение готово',
            'body' => "{$this->kind()} упражнение на {$this->exercise->items_count} слов уже доступно.",
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
        ], true);
    }

    private function kind(): string
    {
        return (int) $this->exercise->type_id === ExerciseTypeCode::weekly->value
            ? 'Недельное'
            : 'Ежедневное';
    }
}
