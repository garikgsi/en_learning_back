<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class AppUpdateController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $versionCode = (int) config('app_update.version_code', 0);
        $versionName = config('app_update.version_name');
        $apkUrl = config('app_update.apk_url');
        $sha256 = strtolower((string) config('app_update.sha256'));

        if (
            $versionCode < 1
            || ! is_string($versionName)
            || $versionName === ''
            || ! is_string($apkUrl)
            || filter_var($apkUrl, FILTER_VALIDATE_URL) === false
            || preg_match('/^[a-f0-9]{64}$/', $sha256) !== 1
        ) {
            return response()->json(['data' => null]);
        }

        $size = config('app_update.size');

        return response()->json([
            'data' => [
                'versionCode' => $versionCode,
                'versionName' => $versionName,
                'apkUrl' => $apkUrl,
                'sha256' => $sha256,
                'size' => is_numeric($size) ? (int) $size : null,
                'releasedAt' => config('app_update.released_at'),
                'releaseNotes' => config('app_update.release_notes'),
                'mandatory' => (bool) config('app_update.mandatory', false),
            ],
        ]);
    }
}
