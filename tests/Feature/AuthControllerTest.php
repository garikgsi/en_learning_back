<?php

namespace Tests\Feature;

use App\Models\AuthSession;
use App\Models\User;
use App\Services\Auth\AuthTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_log_in_and_use_access_token(): void
    {
        $user = User::factory()->create([
            'phone' => '+79991234567',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'phone' => '8 (999) 123-45-67',
            'pinCode' => '1234',
        ]);

        $accessToken = $response
            ->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('tokenType', 'Bearer')
            ->json('accessToken');

        $this->withToken($accessToken)
            ->getJson('/api/v1/users/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id);
    }

    public function test_login_is_limited_to_three_failed_attempts_per_user_per_hour(): void
    {
        User::factory()->create([
            'phone' => '+79991234567',
        ]);

        $credentials = [
            'phone' => '+79991234567',
            'pinCode' => '9999',
        ];

        foreach (range(1, 3) as $attempt) {
            $this->postJson('/api/v1/auth/login', $credentials)
                ->assertUnauthorized()
                ->assertJsonPath('code', 'INVALID_CREDENTIALS');
        }

        $this->postJson('/api/v1/auth/login', [
            'phone' => '+79991234567',
            'pinCode' => '1234',
        ])
            ->assertTooManyRequests()
            ->assertHeader('Retry-After')
            ->assertJsonPath('code', 'LOGIN_RATE_LIMITED');
    }

    public function test_login_limit_is_not_shared_between_users(): void
    {
        User::factory()->create(['phone' => '+79991234567']);
        User::factory()->create(['phone' => '+79997654321']);

        foreach (range(1, 3) as $attempt) {
            $this->postJson('/api/v1/auth/login', [
                'phone' => '+79991234567',
                'pinCode' => '9999',
            ])->assertUnauthorized();
        }

        $this->postJson('/api/v1/auth/login', [
            'phone' => '+79997654321',
            'pinCode' => '1234',
        ])->assertOk();
    }

    public function test_refresh_token_is_rotated(): void
    {
        $user = User::factory()->create();
        $tokens = app(AuthTokenService::class)->issue($user);

        $response = $this->postJson('/api/v1/auth/refresh', [
            'refreshToken' => $tokens['refreshToken'],
        ])->assertOk();

        $this->assertNotSame($tokens['accessToken'], $response->json('accessToken'));
        $this->assertNotSame($tokens['refreshToken'], $response->json('refreshToken'));

        $this->postJson('/api/v1/auth/refresh', [
            'refreshToken' => $tokens['refreshToken'],
        ])->assertUnauthorized();
    }

    public function test_logout_revokes_current_session(): void
    {
        $user = User::factory()->create();
        $tokens = app(AuthTokenService::class)->issue($user);

        $this->withToken($tokens['accessToken'])
            ->postJson('/api/v1/auth/logout')
            ->assertNoContent();

        $this->assertNotNull(AuthSession::query()->sole()->revoked_at);

        $this->withToken($tokens['accessToken'])
            ->getJson('/api/v1/users/me')
            ->assertUnauthorized();
    }

    public function test_login_rejects_phone_with_plus_eight_prefix(): void
    {
        User::factory()->create(['phone' => '+79991234567']);

        $this->postJson('/api/v1/auth/login', [
            'phone' => '+8 (999) 123-45-67',
            'pinCode' => '1234',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('phone');
    }
}
