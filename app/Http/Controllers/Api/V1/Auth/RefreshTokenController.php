<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\RefreshTokenRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Services\Auth\AuthTokenService;
use Illuminate\Http\JsonResponse;

class RefreshTokenController extends Controller
{
    public function __invoke(
        RefreshTokenRequest $request,
        AuthTokenService $tokenService,
    ): JsonResponse {
        $result = $tokenService->refresh($request->validated('refreshToken'));

        return response()->json([
            'user' => (new UserResource($result['session']->user))->resolve(),
            ...$result['tokens'],
        ]);
    }
}
