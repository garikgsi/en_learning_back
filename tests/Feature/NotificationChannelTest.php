<?php

namespace Tests\Feature;

use App\Enums\ExerciseTypeCode;
use App\Models\Exercise;
use App\Models\User;
use App\Models\UserDevice;
use App\Notifications\AppReleaseAvailable;
use App\Notifications\Channels\FcmChannel;
use App\Notifications\DeliverStoredNotificationPush;
use App\Notifications\ExerciseCreated;
use App\Notifications\ExerciseReminder;
use App\Services\Notifications\Contracts\PushGateway;
use App\Services\Notifications\Exceptions\InvalidPushTokenException;
use Carbon\CarbonImmutable;
use Database\Seeders\ExerciseTypesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class NotificationChannelTest extends TestCase
{
    use RefreshDatabase;

    public function test_laravel_notification_persists_journal_before_queueing_fcm_channel(): void
    {
        config()->set('notifications.push.enabled', true);
        Queue::fake();
        $this->seed(ExerciseTypesSeeder::class);
        $user = User::factory()->create();
        $device = $this->createDevice($user);
        $exercise = Exercise::query()->create([
            'user_id' => $user->id,
            'type_id' => ExerciseTypeCode::daily->value,
            'dueDate' => now(),
        ]);

        $user->notify(new ExerciseCreated($exercise));

        $storedNotification = $user->notifications()->sole();
        $this->assertSame('exercise.created', $storedNotification->type);
        Queue::assertPushed(
            SendQueuedNotifications::class,
            fn (SendQueuedNotifications $job): bool => $job->notification instanceof DeliverStoredNotificationPush
                && $job->notification->notificationId === $storedNotification->id
                && $job->notifiables->contains($device),
        );
    }

    public function test_fcm_channel_disables_an_unregistered_push_token(): void
    {
        $user = User::factory()->create();
        $device = $this->createDevice($user);
        $storedNotification = $user->notifications()->create([
            'type' => 'exercise.created',
            'title' => 'Новое упражнение',
            'body' => 'Упражнение готово',
            'data' => ['route' => '/exercises/1'],
        ]);
        $gateway = Mockery::mock(PushGateway::class);
        $gateway->shouldReceive('send')
            ->once()
            ->andThrow(new InvalidPushTokenException);

        (new FcmChannel($gateway))->send(
            $device,
            new DeliverStoredNotificationPush($storedNotification->id),
        );

        $this->assertFalse($device->refresh()->notifications_enabled);
    }

    public function test_exercise_notifications_are_limited_to_daily_and_weekly_types(): void
    {
        $this->seed(ExerciseTypesSeeder::class);
        $user = User::factory()->create();
        $exercise = Exercise::query()->create([
            'user_id' => $user->id,
            'type_id' => ExerciseTypeCode::user->value,
            'dueDate' => now(),
        ]);

        $user->notify(new ExerciseCreated($exercise));
        $user->notify(new ExerciseReminder($exercise));

        $this->assertDatabaseCount('user_notifications', 0);
    }

    public function test_exercise_reminder_is_not_duplicated(): void
    {
        $this->seed(ExerciseTypesSeeder::class);
        $user = User::factory()->create();
        $exercise = Exercise::query()->create([
            'user_id' => $user->id,
            'type_id' => ExerciseTypeCode::daily->value,
            'dueDate' => now(),
        ]);

        $user->notify(new ExerciseReminder($exercise));
        $user->notify(new ExerciseReminder($exercise));

        $this->assertDatabaseCount('user_notifications', 1);
    }

    public function test_push_before_delivery_window_is_delayed_until_noon_in_moscow(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-07-31 07:00:00 UTC'));
        config()->set('notifications.push.enabled', true);
        Queue::fake();
        $user = User::factory()->create();
        $device = $this->createDevice($user);

        $user->notify(new AppReleaseAvailable('0.1.0-rc.15'));

        Queue::assertPushed(
            SendQueuedNotifications::class,
            fn (SendQueuedNotifications $job): bool => $job->notification instanceof DeliverStoredNotificationPush
                && $job->notifiables->contains($device)
                && CarbonImmutable::instance($job->delay)
                    ->equalTo('2026-07-31 09:00:00 UTC'),
        );
    }

    public function test_push_after_delivery_window_is_delayed_until_next_noon(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-07-31 18:00:00 UTC'));
        config()->set('notifications.push.enabled', true);
        Queue::fake();
        $user = User::factory()->create();
        $device = $this->createDevice($user);

        $user->notify(new AppReleaseAvailable('0.1.0-rc.15'));

        Queue::assertPushed(
            SendQueuedNotifications::class,
            fn (SendQueuedNotifications $job): bool => $job->notification instanceof DeliverStoredNotificationPush
                && $job->notifiables->contains($device)
                && CarbonImmutable::instance($job->delay)
                    ->equalTo('2026-08-01 09:00:00 UTC'),
        );
    }

    private function createDevice(User $user): UserDevice
    {
        return $user->devices()->create([
            'installation_id' => (string) Str::uuid(),
            'push_token' => Str::random(100),
            'platform' => 'android',
            'notifications_enabled' => true,
            'last_seen_at' => now(),
        ]);
    }
}
