<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\AppUpdate\LatestAppReleaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AppUpdateController extends Controller
{
    public function __invoke(
        Request $request,
        LatestAppReleaseService $latestAppRelease,
    ): JsonResponse {
        return response()->json([
            'data' => $latestAppRelease->latest(
                $request->boolean('refresh'),
            ),
        ]);
    }
}
