<?php

namespace App\Services\Auth;

use App\Models\AuthSession;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\DB;

class AuthTokenService
{
    private const ACCESS_TOKEN_TTL_MINUTES = 15;

    private const REFRESH_TOKEN_TTL_DAYS = 30;

    /**
     * @return array{accessToken: string, refreshToken: string, tokenType: string, expiresIn: int}
     */
    public function issue(User $user): array
    {
        $accessToken = $this->randomToken();
        $refreshToken = $this->randomToken();

        AuthSession::query()->create([
            'user_id' => $user->id,
            'access_token_hash' => self::hash($accessToken),
            'refresh_token_hash' => self::hash($refreshToken),
            'access_expires_at' => now()->addMinutes(self::ACCESS_TOKEN_TTL_MINUTES),
            'refresh_expires_at' => now()->addDays(self::REFRESH_TOKEN_TTL_DAYS),
        ]);

        return $this->plainTokens($accessToken, $refreshToken);
    }

    /**
     * @return array{session: AuthSession, tokens: array{accessToken: string, refreshToken: string, tokenType: string, expiresIn: int}}
     */
    public function refresh(string $refreshToken): array
    {
        return DB::transaction(function () use ($refreshToken): array {
            $session = AuthSession::query()
                ->where('refresh_token_hash', self::hash($refreshToken))
                ->lockForUpdate()
                ->first();

            if (
                $session === null
                || $session->revoked_at !== null
                || $session->refresh_expires_at->isPast()
            ) {
                throw new AuthenticationException;
            }

            $accessToken = $this->randomToken();
            $newRefreshToken = $this->randomToken();

            $session->update([
                'access_token_hash' => self::hash($accessToken),
                'refresh_token_hash' => self::hash($newRefreshToken),
                'access_expires_at' => now()->addMinutes(self::ACCESS_TOKEN_TTL_MINUTES),
                'refresh_expires_at' => now()->addDays(self::REFRESH_TOKEN_TTL_DAYS),
                'last_used_at' => now(),
            ]);

            return [
                'session' => $session,
                'tokens' => $this->plainTokens($accessToken, $newRefreshToken),
            ];
        });
    }

    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    private function randomToken(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '=');
    }

    /**
     * @return array{accessToken: string, refreshToken: string, tokenType: string, expiresIn: int}
     */
    private function plainTokens(string $accessToken, string $refreshToken): array
    {
        return [
            'accessToken' => $accessToken,
            'refreshToken' => $refreshToken,
            'tokenType' => 'Bearer',
            'expiresIn' => self::ACCESS_TOKEN_TTL_MINUTES * 60,
        ];
    }
}
