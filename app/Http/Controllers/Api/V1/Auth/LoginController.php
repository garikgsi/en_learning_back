<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\User;
use App\Services\Auth\AuthTokenService;
use App\Services\Auth\PinHasher;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\RateLimiter;

class LoginController extends Controller
{
    private const MAX_ATTEMPTS = 3;

    private const DECAY_SECONDS = 3600;

    public function __invoke(
        LoginRequest $request,
        PinHasher $pinHasher,
        AuthTokenService $tokenService,
    ): JsonResponse {
        $data = $request->validated();
        $user = User::query()->where('phone', $data['phone'])->first();
        $rateLimitKey = $this->rateLimitKey($data['phone'], $user);

        if (RateLimiter::tooManyAttempts($rateLimitKey, self::MAX_ATTEMPTS)) {
            return response()
                ->json([
                    'message' => 'Слишком много попыток входа. Повторите позже.',
                    'code' => 'LOGIN_RATE_LIMITED',
                ], 429)
                ->header('Retry-After', (string) RateLimiter::availableIn($rateLimitKey));
        }

        if ($user === null || ! $pinHasher->check($data['pinCode'], $user->pin_hash)) {
            RateLimiter::hit($rateLimitKey, self::DECAY_SECONDS);

            return response()->json([
                'message' => 'Неверный телефон или PIN-код.',
                'code' => 'INVALID_CREDENTIALS',
            ], 401);
        }

        RateLimiter::clear($rateLimitKey);

        return response()->json([
            'user' => (new UserResource($user))->resolve(),
            ...$tokenService->issue($user),
        ]);
    }

    private function rateLimitKey(string $phone, ?User $user): string
    {
        $subject = $user?->id ?? hash('sha256', $phone);

        return "login:user:{$subject}";
    }
}
