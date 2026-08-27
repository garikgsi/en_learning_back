<?php

namespace App\Notifications\Contracts;

interface StoresInNotificationJournal
{
    /**
     * @return array{type: string, title: string, body: string, data: array<string, scalar>}
     */
    public function toJournal(object $notifiable): array;

    public function deduplicationKey(object $notifiable): ?string;
}
