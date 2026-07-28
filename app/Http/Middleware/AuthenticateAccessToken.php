<?php

namespace App\Http\Middleware;

use App\Models\AuthSession;
use App\Services\Auth\AuthTokenService;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateAccessToken
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if ($token === null) {
            throw new AuthenticationException;
        }

        $session = AuthSession::query()
            ->with('user')
            ->where('access_token_hash', AuthTokenService::hash($token))
            ->whereNull('revoked_at')
            ->where('access_expires_at', '>', now())
            ->first();

        if ($session === null) {
            throw new AuthenticationException;
        }

        $request->setUserResolver(fn () => $session->user);
        $request->attributes->set('auth_session', $session);

        $session->update(['last_used_at' => now()]);

        return $next($request);
    }
}
