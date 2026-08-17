<?php

namespace App\Services\AppUpdate;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class LatestAppReleaseService
{
    /**
     * @return array{
     *     versionCode: int,
     *     versionName: string,
     *     apkUrl: string,
     *     sha256: string,
     *     size: int,
     *     releasedAt: string|null,
     *     releaseNotes: string|null,
     *     mandatory: bool
     * }|null
     */
    public function latest(bool $forceRefresh = false): ?array
    {
        $freshCacheKey = $this->cacheKey('fresh');
        $manualRefreshCacheKey = $this->cacheKey('manual-refresh-cooldown');
        $canForceRefresh = $forceRefresh
            && ! Cache::has($manualRefreshCacheKey);

        if ($canForceRefresh) {
            Cache::put(
                $manualRefreshCacheKey,
                true,
                now()->addSeconds($this->manualRefreshCooldownSeconds()),
            );
        }

        $cached = $canForceRefresh ? null : Cache::get($freshCacheKey);

        if (is_array($cached) && array_key_exists('release', $cached)) {
            return $cached['release'];
        }

        try {
            $release = $this->fetchLatestRelease();

            if ($release !== null) {
                Cache::forever($this->cacheKey('last-known-good'), $release);
            } else {
                $release = $this->lastKnownGood();
            }
        } catch (Throwable $exception) {
            Log::warning('Unable to refresh the latest Android release.', [
                'exception' => $exception,
            ]);
            $release = $this->lastKnownGood();
        }

        Cache::put(
            $freshCacheKey,
            ['release' => $release],
            now()->addSeconds($this->cacheTtlSeconds()),
        );

        return $release;
    }

    /**
     * @return array{
     *     versionCode: int,
     *     versionName: string,
     *     apkUrl: string,
     *     sha256: string,
     *     size: int,
     *     releasedAt: string|null,
     *     releaseNotes: string|null,
     *     mandatory: bool
     * }|null
     */
    private function fetchLatestRelease(): ?array
    {
        $repository = (string) config('app_update.github_repository');
        $response = $this->githubRequest()
            ->get("https://api.github.com/repos/{$repository}/releases", [
                'per_page' => 20,
            ])
            ->throw();
        $releases = $response->json();

        if (! is_array($releases)) {
            return null;
        }

        $latestRelease = null;

        foreach ($releases as $release) {
            if (! is_array($release) || ! $this->isReleaseAllowed($release)) {
                continue;
            }

            $manifestAsset = $this->findAsset(
                $release,
                (string) config('app_update.manifest_asset'),
            );

            if ($manifestAsset === null) {
                continue;
            }

            $manifest = $this->githubRequest()
                ->get($manifestAsset['browser_download_url'])
                ->throw()
                ->json();
            $validated = $this->validateManifest($release, $manifest);

            if (
                $validated !== null
                && (
                    $latestRelease === null
                    || $validated['versionCode'] > $latestRelease['versionCode']
                )
            ) {
                $latestRelease = $validated;
            }
        }

        return $latestRelease;
    }

    private function githubRequest(): PendingRequest
    {
        return Http::acceptJson()
            ->withUserAgent('English-Learning-API')
            ->timeout((int) config('app_update.http_timeout_seconds', 5));
    }

    /**
     * @param  array<string, mixed>  $release
     */
    private function isReleaseAllowed(array $release): bool
    {
        if (($release['draft'] ?? true) === true) {
            return false;
        }

        return match ((string) config('app_update.channel', 'prerelease')) {
            'stable' => ($release['prerelease'] ?? true) === false,
            'all' => true,
            default => ($release['prerelease'] ?? false) === true,
        };
    }

    /**
     * @param  array<string, mixed>  $release
     * @return array{name: string, browser_download_url: string, size: int}|null
     */
    private function findAsset(array $release, string $name): ?array
    {
        $assets = $release['assets'] ?? null;

        if (! is_array($assets)) {
            return null;
        }

        foreach ($assets as $asset) {
            if (
                is_array($asset)
                && ($asset['name'] ?? null) === $name
                && is_string($asset['browser_download_url'] ?? null)
                && filter_var($asset['browser_download_url'], FILTER_VALIDATE_URL)
                && is_int($asset['size'] ?? null)
            ) {
                return [
                    'name' => $asset['name'],
                    'browser_download_url' => $asset['browser_download_url'],
                    'size' => $asset['size'],
                ];
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $release
     * @return array{
     *     versionCode: int,
     *     versionName: string,
     *     apkUrl: string,
     *     sha256: string,
     *     size: int,
     *     releasedAt: string|null,
     *     releaseNotes: string|null,
     *     mandatory: bool
     * }|null
     */
    private function validateManifest(array $release, mixed $manifest): ?array
    {
        if (! is_array($manifest) || ($manifest['schemaVersion'] ?? null) !== 1) {
            return null;
        }

        $versionCode = filter_var(
            $manifest['versionCode'] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]],
        );
        $versionName = $manifest['versionName'] ?? null;
        $apkAssetName = $manifest['apkAsset'] ?? null;
        $sha256 = strtolower((string) ($manifest['sha256'] ?? ''));
        $size = filter_var(
            $manifest['size'] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]],
        );
        $tagName = $release['tag_name'] ?? null;

        if (
            $versionCode === false
            || ! is_string($versionName)
            || $versionName === ''
            || ! is_string($tagName)
            || $tagName !== "v{$versionName}"
            || ! is_string($apkAssetName)
            || $apkAssetName === ''
            || preg_match('/^[a-f0-9]{64}$/', $sha256) !== 1
            || $size === false
        ) {
            return null;
        }

        $apkAsset = $this->findAsset($release, $apkAssetName);

        if ($apkAsset === null || $apkAsset['size'] !== $size) {
            return null;
        }

        $releasedAt = $release['published_at'] ?? null;
        $releaseNotes = $release['body'] ?? null;

        return [
            'versionCode' => $versionCode,
            'versionName' => $versionName,
            'apkUrl' => $apkAsset['browser_download_url'],
            'sha256' => $sha256,
            'size' => $size,
            'releasedAt' => is_string($releasedAt) ? $releasedAt : null,
            'releaseNotes' => is_string($releaseNotes) && $releaseNotes !== ''
                ? $releaseNotes
                : null,
            'mandatory' => ($manifest['mandatory'] ?? false) === true,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function lastKnownGood(): ?array
    {
        $release = Cache::get($this->cacheKey('last-known-good'));

        return is_array($release) ? $release : null;
    }

    private function cacheKey(string $suffix): string
    {
        $source = implode('|', [
            (string) config('app_update.github_repository'),
            (string) config('app_update.channel'),
        ]);

        return 'app-update:'.$suffix.':'.hash('sha256', $source);
    }

    private function cacheTtlSeconds(): int
    {
        return max(60, (int) config('app_update.cache_ttl_seconds', 900));
    }

    private function manualRefreshCooldownSeconds(): int
    {
        return max(
            30,
            (int) config(
                'app_update.manual_refresh_cooldown_seconds',
                60,
            ),
        );
    }
}
