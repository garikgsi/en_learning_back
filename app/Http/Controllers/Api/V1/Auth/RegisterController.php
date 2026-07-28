<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\RegisterRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\User;
use App\Services\Auth\AuthTokenService;
use App\Services\Auth\PinHasher;
use Illuminate\Http\JsonResponse;

class RegisterController extends Controller
{
    public function __invoke(
        RegisterRequest $request,
        PinHasher $pinHasher,
        AuthTokenService $tokenService,
    ): JsonResponse {
        $data = $request->validated();

        $user = new User;
        $user->forceFill([
            'name' => $data['name'],
            'phone' => $data['phone'],
            'pin_hash' => $pinHasher->make($data['pinCode']),
        ]);
        $user->save();

        return response()->json([
            'user' => (new UserResource($user))->resolve(),
            ...$tokenService->issue($user),
        ], 201);
    }
}
