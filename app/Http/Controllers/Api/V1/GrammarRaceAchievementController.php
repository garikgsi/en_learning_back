<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\GrammarRace\GrammarRaceAchievementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GrammarRaceAchievementController extends Controller
{
    public function __invoke(Request $request, GrammarRaceAchievementService $service): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return response()->json($service->forUser($user));
    }
}
