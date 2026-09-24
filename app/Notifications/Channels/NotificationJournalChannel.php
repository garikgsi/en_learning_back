<?php

namespace App\Notifications\Channels;

use App\Models\UserNotification;
use App\Notifications\Contracts\SkipsPushDelivery;
use App\Notifications\Contracts\StoresInNotificationJournal;
use App\Notifications\DeliverStoredNotificationPush;
use App\Services\Notifications\PushDeliveryWindow;
use Illuminate\Notifications\Notification;
use LogicException;

class NotificationJournalChannel
{
    public function __construct(
        private readonly PushDeliveryWindow $deliveryWindow,
    ) {}

    public function send(
        object $notifiable,
        Notification $notification,
    ): ?UserNotification {
        if (! $notification instanceof StoresInNotificationJournal) {
            throw new LogicException(
                'Journal notifications must implement StoresInNotificationJournal.',
            );
        }

        $attributes = $notification->toJournal($notifiable);
        $deduplicationKey = $notification->deduplicationKey($notifiable);
        $storedNotification = $deduplicationKey === null
            ? $notifiable->notifications()->create($attributes)
            : $notifiable->notifications()->firstOrCreate(
                ['deduplication_key' => $deduplicationKey],
                $attributes,
            );

        if (! $storedNotification->wasRecentlyCreated
            || $notification instanceof SkipsPushDelivery
            || ! config('notifications.push.enabled')) {
            return $storedNotification;
        }

        $deliveryAt = $this->deliveryWindow->nextDeliveryAt();

        $notifiable->devices()
            ->where('notifications_enabled', true)
            ->each(function ($device) use (
                $deliveryAt,
                $storedNotification,
            ): void {
                $push = new DeliverStoredNotificationPush(
                    $storedNotification->id,
                );

                if ($deliveryAt !== null) {
                    $push->delay($deliveryAt);
                }

                $device->notify($push);
            });

        return $storedNotification;
    }
}
