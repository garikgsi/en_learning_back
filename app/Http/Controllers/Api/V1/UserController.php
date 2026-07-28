<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateUserRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class UserController extends Controller
{
    public function show(Request $request): UserResource
    {
        return new UserResource($this->authenticatedUser($request));
    }

    public function update(UpdateUserRequest $request): UserResource
    {
        $user = $this->authenticatedUser($request);
        $validated = $request->validated();
        $oldAvatarPath = $user->avatar_path;
        $newAvatarPath = null;

        if ($request->hasFile('avatar')) {
            $newAvatarPath = $request->file('avatar')
                ?->storePublicly("avatars/{$user->id}", 'public');

            throw_unless(is_string($newAvatarPath), RuntimeException::class);
        }

        try {
            $user->fill(Arr::except($validated, 'avatar'));

            if ($newAvatarPath !== null) {
                $user->avatar_path = $newAvatarPath;
            }

            $user->save();
        } catch (\Throwable $exception) {
            if ($newAvatarPath !== null) {
                Storage::disk('public')->delete($newAvatarPath);
            }

            throw $exception;
        }

        if ($newAvatarPath !== null && $oldAvatarPath !== null) {
            Storage::disk('public')->delete($oldAvatarPath);
        }

        return new UserResource($user->refresh());
    }

    private function authenticatedUser(Request $request): User
    {
        $user = $request->user();

        abort_unless($user instanceof User, 401);

        return $user;
    }
}
