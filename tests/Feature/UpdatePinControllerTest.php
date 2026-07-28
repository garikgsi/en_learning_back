<?php

namespace Tests\Feature;

use App\Models\AuthSession;
use App\Models\User;
use App\Services\Auth\AuthTokenService;
use App\Services\Auth\PinHasher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdatePinControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_change_pin_and_all_sessions_are_revoked(): void
    {
        $user = User::factory()->create();
        $tokens = app(AuthTokenService::class)->issue($user);
        app(AuthTokenService::class)->issue($user);

        $this->withToken($tokens['accessToken'])
            ->putJson('/api/v1/users/me/pin', [
                'currentPin' => '1234',
                'pinCode' => '5678',
                'pinCodeConfirmation' => '5678',
            ])
            ->assertNoContent();

        $this->assertTrue(app(PinHasher::class)->check('5678', $user->refresh()->pin_hash));
        $this->assertSame(2, AuthSession::query()->whereNotNull('revoked_at')->count());

        $this->withToken($tokens['accessToken'])
            ->getJson('/api/v1/users/me')
            ->assertUnauthorized();
    }

    public function test_current_pin_must_be_correct(): void
    {
        $user = User::factory()->create();
        $tokens = app(AuthTokenService::class)->issue($user);

        $this->withToken($tokens['accessToken'])
            ->putJson('/api/v1/users/me/pin', [
                'currentPin' => '9999',
                'pinCode' => '5678',
                'pinCodeConfirmation' => '5678',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'INVALID_CURRENT_PIN');

        $this->assertTrue(app(PinHasher::class)->check('1234', $user->refresh()->pin_hash));
        $this->assertNull(AuthSession::query()->sole()->revoked_at);
    }

    public function test_new_pin_and_confirmation_must_match(): void
    {
        $user = User::factory()->create();
        $tokens = app(AuthTokenService::class)->issue($user);

        $this->withToken($tokens['accessToken'])
            ->putJson('/api/v1/users/me/pin', [
                'currentPin' => '1234',
                'pinCode' => '5678',
                'pinCodeConfirmation' => '8765',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('pinCodeConfirmation');

        $this->assertTrue(app(PinHasher::class)->check('1234', $user->refresh()->pin_hash));
        $this->assertNull(AuthSession::query()->sole()->revoked_at);
    }
}
