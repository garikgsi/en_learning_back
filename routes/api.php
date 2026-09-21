<?php

use App\Http\Controllers\Api\V1\AdminDailyExerciseController;
use App\Http\Controllers\Api\V1\AppUpdateController;
use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Auth\LogoutController;
use App\Http\Controllers\Api\V1\Auth\RefreshTokenController;
use App\Http\Controllers\Api\V1\Auth\RegisterController;
use App\Http\Controllers\Api\V1\BalanceController;
use App\Http\Controllers\Api\V1\DictionaryController;
use App\Http\Controllers\Api\V1\ExerciseController;
use App\Http\Controllers\Api\V1\MonetizationController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\UpdatePinController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\UserDeviceController;
use App\Http\Controllers\Api\V1\UserWordRepetitionController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::prefix('auth')->group(function (): void {
        Route::post('/register', RegisterController::class);
        Route::post('/login', LoginController::class);
        Route::post('/refresh', RefreshTokenController::class);
    });

    Route::get(
        '/dictionary/words/{word}/audio',
        [DictionaryController::class, 'audio'],
    )->whereNumber('word');
    Route::get(
        '/dictionary/plurals/{plural}/audio',
        [DictionaryController::class, 'pluralAudio'],
    )->whereNumber('plural');

    Route::middleware('access.token')->group(function (): void {
        Route::get('/app-updates/latest', AppUpdateController::class);
        Route::post('/auth/logout', LogoutController::class);
        Route::get('/users/me', [UserController::class, 'show']);
        Route::get('/balance', [BalanceController::class, 'show']);
        Route::post('/balance/withdrawals', [BalanceController::class, 'withdraw']);
        Route::get('/admin/monetization-requests', [MonetizationController::class, 'index']);
        Route::get('/admin/encoin-rate', [MonetizationController::class, 'rate']);
        Route::put('/admin/encoin-rate', [MonetizationController::class, 'updateRate']);
        Route::put('/admin/monetization-requests/{monetizationRequest}/processed', [MonetizationController::class, 'process'])
            ->whereNumber('monetizationRequest');
        Route::get('/admin/users', [AdminDailyExerciseController::class, 'users']);
        Route::post('/admin/exercises/daily', [AdminDailyExerciseController::class, 'store']);
        Route::patch('/users/me', [UserController::class, 'update']);
        Route::put('/users/me/pin', UpdatePinController::class);
        Route::get('/dictionary', [DictionaryController::class, 'index']);
        Route::get('/dictionary/sync', [DictionaryController::class, 'sync']);
        Route::post('/dictionary/lookup', [DictionaryController::class, 'lookup']);
        Route::post('/dictionary/words', [DictionaryController::class, 'store']);
        Route::get('/dictionary/words/{word}', [DictionaryController::class, 'show'])
            ->whereNumber('word');
        Route::patch('/dictionary/words/{word}', [DictionaryController::class, 'update'])
            ->whereNumber('word');
        Route::post(
            '/repetition-list/words',
            UserWordRepetitionController::class,
        );
        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::patch(
            '/notifications/{notification}/read',
            [NotificationController::class, 'markRead'],
        )->whereUuid('notification');
        Route::put(
            '/notification-devices',
            [UserDeviceController::class, 'store'],
        );
        Route::delete(
            '/notification-devices/{installationId}',
            [UserDeviceController::class, 'destroy'],
        )->whereUuid('installationId');
        Route::get('/exercises', [ExerciseController::class, 'index']);
        Route::post('/exercises', [ExerciseController::class, 'store']);
        Route::get('/exercises/current', [ExerciseController::class, 'current']);
        Route::get(
            '/exercises/statistics',
            [ExerciseController::class, 'statistics'],
        );
        Route::get('/exercises/{exercise}', [ExerciseController::class, 'show'])
            ->whereNumber('exercise');
        Route::post('/exercises/complete', [ExerciseController::class, 'complete']);
    });
});
