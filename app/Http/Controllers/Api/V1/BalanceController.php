<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\WithdrawalRequest;
use App\Http\Resources\Api\V1\MonetizationRequestResource;
use App\Models\User;
use App\Services\EnCoinService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BalanceController extends Controller
{
    public function show(Request $request, EnCoinService $service): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return DB::transaction(function () use ($user, $service, $request): JsonResponse {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $service->awardCompletedWeeks($user);

            return response()->json([
                ...$service->balance($user),
                'requests' => MonetizationRequestResource::collection($user->monetizationRequests()->latest('id')->limit(50)->get())->resolve($request),
            ]);
        });
    }

    public function withdraw(WithdrawalRequest $request, EnCoinService $service): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $data = $request->validated();
        $result = $service->requestWithdrawal($user, (int) $data['coins'], $data['clientRequestId']);

        return response()->json([
            'item' => MonetizationRequestResource::make($result['request'])->resolve($request),
        ], $result['created'] ? 201 : 200);
    }
}
