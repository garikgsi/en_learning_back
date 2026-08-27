<?php

namespace App\Notifications;

use App\Notifications\Channels\NotificationJournalChannel;
use App\Notifications\Contracts\StoresInNotificationJournal;
use Illuminate\Notifications\Notification;

class AppReleaseAvailable extends Notification implements StoresInNotificationJournal
{
    public function __construct(
        private readonly string $version,
    ) {}

    /**
     * @return list<class-string>
     */
    public function via(object $notifiable): array
    {
        return [NotificationJournalChannel::class];
    }

    public function deduplicationKey(object $notifiable): string
    {
        return "app-release:{$this->version}:user:{$notifiable->id}";
    }

    /**
     * @return array{type: string, title: string, body: string, data: array<string, scalar>}
     */
    public function toJournal(object $notifiable): array
    {
        return [
            'type' => 'app.release.available',
            'title' => 'Доступна новая версия',
            'body' => "Версия {$this->version} готова к установке.",
            'data' => [
                'route' => '/update',
                'version' => $this->version,
            ],
        ];
    }
}
