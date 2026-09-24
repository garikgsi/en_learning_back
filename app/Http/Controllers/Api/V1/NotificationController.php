<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\NotificationSyncRequest;
use App\Http\Resources\Api\V1\UserNotificationResource;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(NotificationSyncRequest $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $afterSequence = (int) ($request->validated('afterSequence') ?? 0);
        $perPage = (int) ($request->validated('perPage') ?? 100);
        $notifications = UserNotification::query()
            ->where('user_id', $user->id)
            ->where('id', '>', $afterSequence)
            ->orderBy('id')
            ->limit($perPage + 1)
            ->get();
        $hasMore = $notifications->count() > $perPage;
        $items = $notifications->take($perPage)->values();

        return response()->json([
            'items' => UserNotificationResource::collection($items)
                ->resolve($request),
            'nextSequence' => $items->last()?->id ?? $afterSequence,
            'hasMore' => $hasMore,
            'unreadCount' => UserNotification::query()
                ->where('user_id', $user->id)
                ->whereNull('read_at')
                ->count(),
        ]);
    }

    public function markRead(
        string $notification,
        Request $request,
    ): JsonResponse {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $item = UserNotification::query()
            ->where('public_id', $notification)
            ->where('user_id', $user->id)
            ->firstOrFail();

        if ($item->read_at === null) {
            $item->update(['read_at' => now()]);
        }

        return response()->json(
            UserNotificationResource::make($item->refresh())
                ->resolve($request),
        );
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $readAt = now();

        UserNotification::query()
            ->where('user_id', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => $readAt]);

        return response()->json([
            'readAt' => $readAt->toISOString(),
            'unreadCount' => 0,
        ]);
    }
}
