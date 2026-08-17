<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Auth\AuthTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AppUpdateControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config()->set('app_update.github_repository', 'owner/app');
        config()->set('app_update.channel', 'prerelease');
        config()->set('app_update.manifest_asset', 'update-manifest.json');
        config()->set('app_update.cache_ttl_seconds', 60);
        config()->set('app_update.http_timeout_seconds', 1);
    }

    public function test_it_returns_the_latest_release_from_github_manifest(): void
    {
        $this->fakeSuccessfulRelease();
        $user = User::factory()->create();

        $this->withToken($this->accessToken($user))
            ->getJson('/api/v1/app-updates/latest')
            ->assertOk()
            ->assertJsonPath('data.versionCode', 9)
            ->assertJsonPath('data.versionName', '0.1.0-rc.9')
            ->assertJsonPath('data.sha256', str_repeat('a', 64))
            ->assertJsonPath('data.size', 123456)
            ->assertJsonPath('data.apkUrl', $this->apkUrl())
            ->assertJsonPath('data.releaseNotes', 'Improved interface')
            ->assertJsonPath('data.mandatory', false);

        Http::assertSentCount(2);
    }

    public function test_it_uses_the_fresh_cache_without_repeating_github_requests(): void
    {
        $this->fakeSuccessfulRelease();
        $user = User::factory()->create();
        $token = $this->accessToken($user);

        $this->withToken($token)
            ->getJson('/api/v1/app-updates/latest')
            ->assertOk();
        $this->withToken($token)
            ->getJson('/api/v1/app-updates/latest')
            ->assertOk()
            ->assertJsonPath('data.versionCode', 9);

        Http::assertSentCount(2);
    }

    public function test_it_returns_the_last_valid_release_when_github_is_unavailable(): void
    {
        $this->fakeSuccessfulRelease();
        $user = User::factory()->create();
        $token = $this->accessToken($user);

        $this->withToken($token)
            ->getJson('/api/v1/app-updates/latest')
            ->assertOk()
            ->assertJsonPath('data.versionCode', 9);

        $this->travel(61)->seconds();
        Http::fake([
            'api.github.com/*' => Http::response([], 503),
        ]);

        $this->withToken($token)
            ->getJson('/api/v1/app-updates/latest')
            ->assertOk()
            ->assertJsonPath('data.versionCode', 9);
    }

    public function test_it_returns_no_release_for_an_invalid_manifest(): void
    {
        Http::fake([
            'api.github.com/repos/owner/app/releases*' => Http::response([
                $this->githubRelease(),
            ]),
            $this->manifestUrl() => Http::response([
                ...$this->manifest(),
                'sha256' => 'invalid',
            ]),
        ]);
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

    private function fakeSuccessfulRelease(): void
    {
        Http::fake([
            'api.github.com/repos/owner/app/releases*' => Http::response([
                $this->githubRelease(),
            ]),
            $this->manifestUrl() => Http::response($this->manifest()),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function githubRelease(): array
    {
        return [
            'draft' => false,
            'prerelease' => true,
            'tag_name' => 'v0.1.0-rc.9',
            'published_at' => '2026-08-17T08:41:17Z',
            'body' => 'Improved interface',
            'assets' => [
                [
                    'name' => 'update-manifest.json',
                    'browser_download_url' => $this->manifestUrl(),
                    'size' => 256,
                ],
                [
                    'name' => 'en-learning-v0.1.0-rc.9-debug.apk',
                    'browser_download_url' => $this->apkUrl(),
                    'size' => 123456,
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function manifest(): array
    {
        return [
            'schemaVersion' => 1,
            'versionCode' => 9,
            'versionName' => '0.1.0-rc.9',
            'apkAsset' => 'en-learning-v0.1.0-rc.9-debug.apk',
            'sha256' => str_repeat('A', 64),
            'size' => 123456,
            'mandatory' => false,
        ];
    }

    private function manifestUrl(): string
    {
        return 'https://github.com/owner/app/releases/download/v0.1.0-rc.9/update-manifest.json';
    }

    private function apkUrl(): string
    {
        return 'https://github.com/owner/app/releases/download/v0.1.0-rc.9/en-learning-v0.1.0-rc.9-debug.apk';
    }
}
