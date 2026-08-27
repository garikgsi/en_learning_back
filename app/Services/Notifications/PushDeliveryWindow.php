<?php

namespace App\Services\Notifications;

use Carbon\CarbonImmutable;
use DateTimeInterface;

class PushDeliveryWindow
{
    public function nextDeliveryAt(): ?DateTimeInterface
    {
        $timezone = config('notifications.delivery_window.timezone');
        $now = CarbonImmutable::now($timezone);
        $startsAt = $now->setTimeFromTimeString(
            config('notifications.delivery_window.starts_at'),
        );
        $endsAt = $now->setTimeFromTimeString(
            config('notifications.delivery_window.ends_at'),
        );

        if ($now->lessThan($startsAt)) {
            return $startsAt->utc();
        }

        if ($now->greaterThan($endsAt)) {
            return $startsAt->addDay()->utc();
        }

        return null;
    }
}
