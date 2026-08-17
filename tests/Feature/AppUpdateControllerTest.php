<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Auth\AuthTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppUpdateControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_the_configured_release(): void
    {
        config()->set('app_update', [
            'version_code' => 8,
            'version_name' => '0.1.0-rc.8',
            'apk_url' => 'https://downloads.example.test/en-learning.apk',
            'sha256' => str_repeat('A', 64),
            'size' => '123456',
            'released_at' => '2026-08-17T08:00:00Z',
            'release_notes' => 'Исправления и новые упражнения',
            'mandatory' => false,
        ]);
        $user = User::factory()->create();

        $this->withToken($this->accessToken($user))
            ->getJson('/api/v1/app-updates/latest')
            ->assertOk()
            ->assertJsonPath('data.versionCode', 8)
            ->assertJsonPath('data.versionName', '0.1.0-rc.8')
            ->assertJsonPath('data.sha256', str_repeat('a', 64))
            ->assertJsonPath('data.size', 123456)
            ->assertJsonPath('data.mandatory', false);
    }

    public function test_it_returns_no_release_when_configuration_is_incomplete(): void
    {
        config()->set('app_update.version_code', 8);
        config()->set('app_update.version_name', '0.1.0-rc.8');
        config()->set('app_update.apk_url', null);
        config()->set('app_update.sha256', null);
        $user = User::factory()->create();

        $this->withToken($this->accessToken($user))
            ->getJson('/api/v1/app-updates/latest')
            ->assertOk()
            ->assertJsonPath('data', null);
    }

    public function test_it_requires_authentication(): void
    {
        $this->getJson('/api/v1/app-updates/latest')
            ->assertUnauthorized();
    }

    private function accessToken(User $user): string
    {
        return app(AuthTokenService::class)->issue($user)['accessToken'];
    }
}
