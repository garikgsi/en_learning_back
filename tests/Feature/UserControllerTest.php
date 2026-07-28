<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Auth\AuthTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UserControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_update_trims_and_updates_name(): void
    {
        $user = User::factory()->create();

        $this->withToken($this->accessToken($user))
            ->patchJson('/api/v1/users/me', ['name' => '  New name  '])
            ->assertOk()
            ->assertJsonPath('data.name', 'New name');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'New name',
        ]);
    }

    public function test_update_stores_avatar_and_deletes_previous_one(): void
    {
        Storage::fake('public');

        $oldAvatarPath = 'avatars/old.webp';
        Storage::disk('public')->put($oldAvatarPath, 'old avatar');

        $user = User::factory()->create(['avatar_path' => $oldAvatarPath]);

        $this->withToken($this->accessToken($user))
            ->patch('/api/v1/users/me', [
                'avatar' => UploadedFile::fake()->image('avatar.png', 300, 300),
            ], ['Accept' => 'application/json'])
            ->assertOk();

        $avatarPath = $user->refresh()->avatar_path;

        $this->assertNotNull($avatarPath);
        Storage::disk('public')->assertExists($avatarPath);
        Storage::disk('public')->assertMissing($oldAvatarPath);
    }

    public function test_update_rejects_invalid_avatar(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();

        $this->withToken($this->accessToken($user))
            ->patch('/api/v1/users/me', [
                'avatar' => UploadedFile::fake()->create('avatar.pdf', 10, 'application/pdf'),
            ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('avatar');
    }

    public function test_update_requires_an_authenticated_user(): void
    {
        $this->patchJson('/api/v1/users/me', ['name' => 'New name'])
            ->assertUnauthorized();
    }

    private function accessToken(User $user): string
    {
        return app(AuthTokenService::class)->issue($user)['accessToken'];
    }
}
