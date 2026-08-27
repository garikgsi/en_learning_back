<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Auth\AuthTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class UserDeviceControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_refresh_and_remove_a_device(): void
    {
        $user = User::factory()->create();
        $token = $this->accessToken($user);
        $installationId = (string) Str::uuid();

        $this->withToken($token)
            ->putJson('/api/v1/notification-devices', [
                'installationId' => $installationId,
                'pushToken' => 'first-token',
                'platform' => 'android',
            ])
            ->assertOk()
            ->assertJsonPath('installationId', $installationId)
            ->assertJsonPath('notificationsEnabled', true);

        $this->withToken($token)
            ->putJson('/api/v1/notification-devices', [
                'installationId' => $installationId,
                'pushToken' => 'rotated-token',
                'platform' => 'android',
                'notificationsEnabled' => false,
            ])
            ->assertOk()
            ->assertJsonPath('notificationsEnabled', false);

        $this->assertDatabaseCount('user_devices', 1);
        $this->assertDatabaseHas('user_devices', [
            'user_id' => $user->id,
            'installation_id' => $installationId,
            'push_token' => 'rotated-token',
            'notifications_enabled' => false,
        ]);

        $this->withToken($token)
            ->deleteJson("/api/v1/notification-devices/{$installationId}")
            ->assertNoContent();

        $this->assertDatabaseCount('user_devices', 0);
    }

    public function test_device_registration_is_validated_and_requires_authentication(): void
    {
        $this->putJson('/api/v1/notification-devices', [])
            ->assertUnauthorized();

        $user = User::factory()->create();

        $this->withToken($this->accessToken($user))
            ->putJson('/api/v1/notification-devices', [
                'installationId' => 'not-a-uuid',
                'pushToken' => '',
                'platform' => 'web',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'installationId',
                'pushToken',
                'platform',
            ]);
    }

    private function accessToken(User $user): string
    {
        return app(AuthTokenService::class)->issue($user)['accessToken'];
    }
}
