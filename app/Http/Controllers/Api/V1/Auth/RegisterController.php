<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\RegisterRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\User;
use App\Services\Auth\AuthTokenService;
use App\Services\Auth\PinHasher;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class RegisterController extends Controller
{
    public function __invoke(
        RegisterRequest $request,
        PinHasher $pinHasher,
        AuthTokenService $tokenService,
    ): JsonResponse {
        $data = $request->validated();

        [$user, $tokens] = DB::transaction(function () use ($data, $pinHasher, $tokenService, $request): array {
            $user = new User;
            $user->forceFill([
                'name' => $data['name'],
                'phone' => $data['phone'],
                'pin_hash' => $pinHasher->make($data['pinCode']),
            ]);
            $user->save();

            if ($request->hasFile('avatar')) {
                $avatarPath = $request->file('avatar')
                    ?->storePublicly("avatars/{$user->id}", 'public');

                throw_unless(is_string($avatarPath), RuntimeException::class);
            }

            try {

                if (isset($avatarPath)) {
                    $user->avatar_path = $avatarPath;
                }

                $user->save();
            } catch (\Throwable $exception) {
                if (isset($avatarPath)) {
                    Storage::disk('public')->delete($avatarPath);
                }

                throw $exception;
            }

            $user->info()->create([
                'first_grade_year' => $data['firstGradeYear'],
            ]);

            return [$user, $tokenService->issue($user)];
        });

        return response()->json([
            'user' => (new UserResource($user))->resolve(),
            ...$tokens,
        ], 201);
    }
}
