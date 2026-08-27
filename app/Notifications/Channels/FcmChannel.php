<?php

namespace App\Notifications\Channels;

use App\Models\UserDevice;
use App\Models\UserNotification;
use App\Notifications\DeliverStoredNotificationPush;
use App\Services\Notifications\Contracts\PushGateway;
use App\Services\Notifications\Exceptions\InvalidPushTokenException;
use Illuminate\Notifications\Notification;
use LogicException;

class FcmChannel
{
    public function __construct(
        private readonly PushGateway $pushGateway,
    ) {}

    public function send(
        object $notifiable,
        Notification $notification,
    ): void {
        if (! $notifiable instanceof UserDevice
            || ! $notification instanceof DeliverStoredNotificationPush) {
            throw new LogicException(
                'The FCM channel requires a user device and stored push notification.',
            );
        }

        $storedNotification = UserNotification::query()
            ->find($notification->notificationId);

        if ($storedNotification === null
            || ! $notifiable->notifications_enabled
            || $notifiable->user_id !== $storedNotification->user_id) {
            return;
        }

        try {
            $this->pushGateway->send(
                $notifiable->push_token,
                $storedNotification->title,
                $storedNotification->body,
                [
                    ...$storedNotification->data,
                    'notificationId' => $storedNotification->public_id,
                    'type' => $storedNotification->type,
                ],
            );
        } catch (InvalidPushTokenException) {
            $notifiable->update(['notifications_enabled' => false]);
        }
    }
}
