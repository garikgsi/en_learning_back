<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdatePinRequest;
use App\Models\User;
use App\Services\Auth\PinHasher;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class UpdatePinController extends Controller
{
    public function __invoke(UpdatePinRequest $request, PinHasher $pinHasher): Response|JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $data = $request->validated();

        if (! $pinHasher->check($data['currentPin'], $user->pin_hash)) {
            return response()->json([
                'message' => 'Текущий PIN-код указан неверно.',
                'code' => 'INVALID_CURRENT_PIN',
                'errors' => [
                    'currentPin' => ['Текущий PIN-код указан неверно.'],
                ],
            ], 422);
        }

        $user->forceFill([
            'pin_hash' => $pinHasher->make($data['pinCode']),
        ])->save();

        $user->authSessions()->update(['revoked_at' => now()]);

        return response()->noContent();
    }
}
