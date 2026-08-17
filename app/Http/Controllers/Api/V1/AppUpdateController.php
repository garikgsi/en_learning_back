<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\AppUpdate\LatestAppReleaseService;
use Illuminate\Http\JsonResponse;

class AppUpdateController extends Controller
{
    public function __invoke(
        LatestAppReleaseService $latestAppRelease,
    ): JsonResponse {
        return response()->json([
            'data' => $latestAppRelease->latest(),
        ]);
    }
}
