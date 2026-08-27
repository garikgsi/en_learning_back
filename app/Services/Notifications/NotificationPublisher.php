<?php

namespace App\Services\Notifications;

use App\Enums\ExerciseTypeCode;
use App\Jobs\SendUserNotificationPush;
use App\Models\Exercise;
use App\Models\User;
use App\Models\UserNotification;
use Carbon\CarbonImmutable;
use DateTimeInterface;

class NotificationPublisher
{
    public function exerciseCreated(Exercise $exercise): ?UserNotification
    {
        $exercise->loadMissing(['type', 'user'])->loadCount('items');

        if (! $this->isNotifiableExercise($exercise)) {
            return null;
        }

        return $this->publish(
            $exercise->user,
            'exercise.created',
            'Новое упражнение готово',
            "{$this->exerciseKind($exercise)} упражнение на {$exercise->items_count} слов уже доступно.",
            [
                'exerciseId' => $exercise->id,
                'route' => "/exercises/{$exercise->id}",
            ],
            "exercise:{$exercise->id}:created",
        );
    }

    public function exerciseReminder(Exercise $exercise): ?UserNotification
    {
        $exercise->loadMissing(['type', 'user']);

        if (! $this->isNotifiableExercise($exercise)
            || $exercise->completions()->exists()) {
            return null;
        }

        return $this->publish(
            $exercise->user,
            'exercise.reminder',
            'Упражнение ждёт вас',
            "{$this->exerciseKind($exercise)} упражнение ещё не пройдено.",
            [
                'exerciseId' => $exercise->id,
                'route' => "/exercises/{$exercise->id}",
            ],
            "exercise:{$exercise->id}:reminder",
        );
    }

    public function appReleaseAvailable(
        User $user,
        string $version,
    ): UserNotification {
        return $this->publish(
            $user,
            'app.release.available',
            'Доступна новая версия',
            "Версия {$version} готова к установке.",
            [
                'route' => '/update',
                'version' => $version,
            ],
            "app-release:{$version}:user:{$user->id}",
        );
    }

    /**
     * @param  array<string, scalar>  $data
     */
    public function publish(
        User $user,
        string $type,
        string $title,
        string $body,
        array $data = [],
        ?string $deduplicationKey = null,
    ): UserNotification {
        $attributes = [
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'data' => $data,
        ];

        $notification = $deduplicationKey === null
            ? $user->notifications()->create($attributes)
            : $user->notifications()->firstOrCreate(
                ['deduplication_key' => $deduplicationKey],
                $attributes,
            );

        if (! $notification->wasRecentlyCreated
            || ! config('notifications.push.enabled')) {
            return $notification;
        }

        $deliveryAt = $this->nextPushDeliveryAt();

        $user->devices()
            ->where('notifications_enabled', true)
            ->pluck('id')
            ->each(function (string $deviceId) use (
                $deliveryAt,
                $notification,
            ): void {
                $dispatch = SendUserNotificationPush::dispatch(
                    $notification->id,
                    $deviceId,
                );

                if ($deliveryAt !== null) {
                    $dispatch->delay($deliveryAt);
                }

                $dispatch->afterCommit();
            });

        return $notification;
    }

    private function exerciseKind(Exercise $exercise): string
    {
        return (int) $exercise->type_id === ExerciseTypeCode::weekly->value
            ? 'Недельное'
            : 'Ежедневное';
    }

    private function isNotifiableExercise(Exercise $exercise): bool
    {
        return in_array((int) $exercise->type_id, [
            ExerciseTypeCode::daily->value,
            ExerciseTypeCode::weekly->value,
        ], true);
    }

    private function nextPushDeliveryAt(): ?DateTimeInterface
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
