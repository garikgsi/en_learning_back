<?php

namespace App\Jobs;

use App\Models\UserDevice;
use App\Models\UserNotification;
use App\Services\Notifications\Contracts\PushGateway;
use App\Services\Notifications\Exceptions\InvalidPushTokenException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendUserNotificationPush implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 60, 300];

    public function __construct(
        public readonly int $notificationId,
        public readonly string $deviceId,
    ) {}

    public function handle(PushGateway $pushGateway): void
    {
        $notification = UserNotification::query()->find($this->notificationId);
        $device = UserDevice::query()->find($this->deviceId);

        if ($notification === null
            || $device === null
            || ! $device->notifications_enabled
            || $device->user_id !== $notification->user_id) {
            return;
        }

        try {
            $pushGateway->send(
                $device->push_token,
                $notification->title,
                $notification->body,
                [
                    ...$notification->data,
                    'notificationId' => $notification->public_id,
                    'type' => $notification->type,
                ],
            );
        } catch (InvalidPushTokenException) {
            $device->update(['notifications_enabled' => false]);
        }
    }
}
