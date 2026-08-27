<?php

namespace App\Services\Notifications;

use App\Services\Notifications\Contracts\PushGateway;
use App\Services\Notifications\Exceptions\InvalidPushTokenException;
use Google\Auth\Credentials\ServiceAccountCredentials;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class FirebasePushGateway implements PushGateway
{
    private const string MESSAGING_SCOPE =
        'https://www.googleapis.com/auth/firebase.messaging';

    /**
     * @param  array<string, scalar>  $data
     */
    public function send(
        string $pushToken,
        string $title,
        string $body,
        array $data,
    ): void {
        $projectId = $this->requiredConfig('project_id');
        $apiUrl = rtrim($this->requiredConfig('api_url'), '/');
        $response = Http::acceptJson()
            ->withToken($this->accessToken())
            ->post("{$apiUrl}/projects/{$projectId}/messages:send", [
                'message' => [
                    'token' => $pushToken,
                    'notification' => [
                        'title' => $title,
                        'body' => $body,
                    ],
                    'data' => array_map(
                        fn (mixed $value): string => match (true) {
                            is_bool($value) => $value ? 'true' : 'false',
                            default => (string) $value,
                        },
                        $data,
                    ),
                    'android' => [
                        'notification' => [
                            'channel_id' => $this->requiredConfig(
                                'android_channel_id',
                            ),
                        ],
                    ],
                ],
            ]);

        if ($response->successful()) {
            return;
        }

        if ($this->isInvalidRegistration($response)) {
            throw new InvalidPushTokenException(
                'Firebase rejected an expired or unregistered push token.',
            );
        }

        throw new RuntimeException(sprintf(
            'Firebase push failed with HTTP %d: %s',
            $response->status(),
            $response->json('error.message') ?? $response->body(),
        ));
    }

    private function accessToken(): string
    {
        $credentialsPath = $this->requiredConfig('credentials');
        $cacheKey = 'firebase-messaging:access-token:'
            .hash('sha256', $credentialsPath);
        $cachedToken = Cache::get($cacheKey);

        if (is_string($cachedToken) && $cachedToken !== '') {
            return $cachedToken;
        }

        if (! is_readable($credentialsPath)) {
            throw new RuntimeException(
                "Firebase credentials are not readable at {$credentialsPath}.",
            );
        }

        $credentialsJson = file_get_contents($credentialsPath);
        $credentials = json_decode(
            $credentialsJson === false ? '' : $credentialsJson,
            true,
        );

        if (! is_array($credentials)
            || ($credentials['type'] ?? null) !== 'service_account') {
            throw new RuntimeException(
                'Firebase credentials must contain a service account JSON key.',
            );
        }

        $auth = new ServiceAccountCredentials(
            [self::MESSAGING_SCOPE],
            $credentials,
        );
        $token = $auth->fetchAuthToken();
        $accessToken = $token['access_token'] ?? null;

        if (! is_string($accessToken) || $accessToken === '') {
            throw new RuntimeException(
                'Google Auth did not return a Firebase access token.',
            );
        }

        $ttl = max(60, ((int) ($token['expires_in'] ?? 3600)) - 60);
        Cache::put($cacheKey, $accessToken, $ttl);

        return $accessToken;
    }

    private function isInvalidRegistration(Response $response): bool
    {
        $details = $response->json('error.details');

        if (! is_array($details)) {
            return false;
        }

        foreach ($details as $detail) {
            if (is_array($detail)
                && ($detail['errorCode'] ?? null) === 'UNREGISTERED') {
                return true;
            }
        }

        return false;
    }

    private function requiredConfig(string $key): string
    {
        $value = config("notifications.firebase.{$key}");

        if (! is_string($value) || trim($value) === '') {
            throw new RuntimeException(
                "Firebase configuration [{$key}] is missing.",
            );
        }

        return trim($value);
    }
}
