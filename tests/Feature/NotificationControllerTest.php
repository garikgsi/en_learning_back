<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserNotification;
use App\Services\Auth\AuthTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_incrementally_sync_only_their_notifications(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $first = $this->createNotification($user, 'Первое');
        $second = $this->createNotification($user, 'Второе');
        $this->createNotification($otherUser, 'Чужое');

        $response = $this->withToken($this->accessToken($user))
            ->getJson('/api/v1/notifications?perPage=1');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.id', $first->public_id)
            ->assertJsonPath('items.0.sequence', $first->id)
            ->assertJsonPath('items.0.data.route', '/exercises/1')
            ->assertJsonPath('nextSequence', $first->id)
            ->assertJsonPath('hasMore', true)
            ->assertJsonPath('unreadCount', 2);

        $this->withToken($this->accessToken($user))
            ->getJson("/api/v1/notifications?afterSequence={$first->id}")
            ->assertOk()
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.id', $second->public_id)
            ->assertJsonPath('nextSequence', $second->id)
            ->assertJsonPath('hasMore', false);
    }

    public function test_user_can_mark_only_their_notification_as_read(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $notification = $this->createNotification($user, 'Прочитать');
        $foreignNotification = $this->createNotification($otherUser, 'Чужое');
        $token = $this->accessToken($user);

        $this->withToken($token)
            ->patchJson(
                "/api/v1/notifications/{$notification->public_id}/read",
            )
            ->assertOk()
            ->assertJsonPath('id', $notification->public_id)
            ->assertJsonPath('readAt', fn ($value): bool => is_string($value));

        $this->assertNotNull($notification->refresh()->read_at);

        $this->withToken($token)
            ->patchJson(
                "/api/v1/notifications/{$foreignNotification->public_id}/read",
            )
            ->assertNotFound();
    }

    public function test_notification_endpoints_require_authentication(): void
    {
        $this->getJson('/api/v1/notifications')->assertUnauthorized();
    }

    private function createNotification(
        User $user,
        string $title,
    ): UserNotification {
        return $user->notifications()->create([
            'type' => 'exercise.created',
            'title' => $title,
            'body' => 'Текст уведомления',
            'data' => ['route' => '/exercises/1'],
        ]);
    }

    private function accessToken(User $user): string
    {
        return app(AuthTokenService::class)->issue($user)['accessToken'];
    }
}
