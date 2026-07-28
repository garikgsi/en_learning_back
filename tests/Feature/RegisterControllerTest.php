<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Auth\PinHasher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_with_normalized_phone(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => '  Ivan  ',
            'phone' => '8 (999) 123-45-67',
            'pinCode' => '1234',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('user.name', 'Ivan')
            ->assertJsonPath('user.phone', '+79991234567')
            ->assertJsonStructure([
                'user',
                'accessToken',
                'refreshToken',
                'tokenType',
                'expiresIn',
            ])
            ->assertJsonMissingPath('user.pin_hash')
            ->assertJsonMissingPath('user.pinCode');

        $user = User::query()->sole();

        $this->assertTrue(app(PinHasher::class)->check('1234', $user->pin_hash));
    }

    public function test_registration_rejects_duplicate_phone(): void
    {
        User::factory()->create(['phone' => '+79991234567']);

        $this->postJson('/api/v1/auth/register', [
            'name' => 'Ivan',
            'phone' => '+79991234567',
            'pinCode' => '1234',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('phone');
    }

    public function test_registration_validates_pin(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'name' => 'Ivan',
            'phone' => '+79991234567',
            'pinCode' => '123',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('pinCode');
    }

    public function test_registration_rejects_phone_with_plus_eight_prefix(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'name' => 'Ivan',
            'phone' => '+8 (999) 123-45-67',
            'pinCode' => '1234',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('phone');
    }
}
