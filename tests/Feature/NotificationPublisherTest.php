<?php

namespace Tests\Feature;

use App\Enums\ExerciseTypeCode;
use App\Jobs\SendUserNotificationPush;
use App\Models\Exercise;
use App\Models\ExerciseType;
use App\Models\User;
use App\Models\UserDevice;
use App\Services\Notifications\Contracts\PushGateway;
use App\Services\Notifications\Exceptions\InvalidPushTokenException;
use App\Services\Notifications\NotificationPublisher;
use Carbon\CarbonImmutable;
use Database\Seeders\ExerciseTypesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class NotificationPublisherTest extends TestCase
{
    use RefreshDatabase;

    public function test_publisher_persists_notification_before_queueing_push(): void
    {
        config()->set('notifications.push.enabled', true);
        Queue::fake();
        $this->seed(ExerciseTypesSeeder::class);
        $user = User::factory()->create();
        $device = $this->createDevice($user);
        $exercise = Exercise::query()->create([
            'user_id' => $user->id,
            'type_id' => ExerciseType::forCode(ExerciseTypeCode::daily)->id,
            'dueDate' => now(),
        ]);

        $notification = app(NotificationPublisher::class)
            ->exerciseCreated($exercise);

        $this->assertDatabaseHas('user_notifications', [
            'id' => $notification->id,
            'user_id' => $user->id,
            'type' => 'exercise.created',
        ]);
        Queue::assertPushed(
            SendUserNotificationPush::class,
            fn (SendUserNotificationPush $job): bool => $job->notificationId === $notification->id
                && $job->deviceId === $device->id,
        );
    }

    public function test_job_disables_an_unregistered_push_token(): void
    {
        $user = User::factory()->create();
        $device = $this->createDevice($user);
        $notification = $user->notifications()->create([
            'type' => 'exercise.created',
            'title' => 'Новое упражнение',
            'body' => 'Упражнение готово',
            'data' => ['route' => '/exercises/1'],
        ]);
        $gateway = Mockery::mock(PushGateway::class);
        $gateway->shouldReceive('send')
            ->once()
            ->andThrow(new InvalidPushTokenException);

        (new SendUserNotificationPush(
            $notification->id,
            $device->id,
        ))->handle($gateway);

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
        $publisher = app(NotificationPublisher::class);

        $this->assertNull($publisher->exerciseCreated($exercise));
        $this->assertNull($publisher->exerciseReminder($exercise));
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
        $publisher = app(NotificationPublisher::class);

        $first = $publisher->exerciseReminder($exercise);
        $second = $publisher->exerciseReminder($exercise);

        $this->assertNotNull($first);
        $this->assertTrue($first->is($second));
        $this->assertDatabaseCount('user_notifications', 1);
    }

    public function test_push_before_delivery_window_is_delayed_until_noon_in_moscow(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-07-31 07:00:00 UTC'));
        config()->set('notifications.push.enabled', true);
        Queue::fake();
        $user = User::factory()->create();
        $device = $this->createDevice($user);

        $notification = app(NotificationPublisher::class)
            ->appReleaseAvailable($user, '0.1.0-rc.15');

        Queue::assertPushed(
            SendUserNotificationPush::class,
            fn (SendUserNotificationPush $job): bool => $job->notificationId === $notification->id
                && $job->deviceId === $device->id
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

        $notification = app(NotificationPublisher::class)
            ->appReleaseAvailable($user, '0.1.0-rc.15');

        Queue::assertPushed(
            SendUserNotificationPush::class,
            fn (SendUserNotificationPush $job): bool => $job->notificationId === $notification->id
                && $job->deviceId === $device->id
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
