<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UserDeviceStoreRequest;
use App\Models\User;
use App\Models\UserDevice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class UserDeviceController extends Controller
{
    public function store(UserDeviceStoreRequest $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $data = $request->validated();

        $device = DB::transaction(function () use ($data, $user): UserDevice {
            $device = UserDevice::query()
                ->where('installation_id', $data['installationId'])
                ->lockForUpdate()
                ->first();

            $device ??= UserDevice::query()
                ->where('push_token', $data['pushToken'])
                ->lockForUpdate()
                ->first();

            $device ??= new UserDevice;
            $device->fill([
                'user_id' => $user->id,
                'installation_id' => $data['installationId'],
                'push_token' => $data['pushToken'],
                'platform' => $data['platform'],
                'notifications_enabled' => $data['notificationsEnabled'] ?? true,
                'last_seen_at' => now(),
            ]);
            $device->save();

            return $device;
        });

        return response()->json([
            'installationId' => $device->installation_id,
            'platform' => $device->platform,
            'notificationsEnabled' => $device->notifications_enabled,
            'lastSeenAt' => $device->last_seen_at->toISOString(),
        ]);
    }

    public function destroy(
        string $installationId,
        Request $request,
    ): Response {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        UserDevice::query()
            ->where('user_id', $user->id)
            ->where('installation_id', $installationId)
            ->delete();

        return response()->noContent();
    }
}
