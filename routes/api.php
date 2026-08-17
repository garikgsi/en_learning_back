<?php

use App\Http\Controllers\Api\V1\AppUpdateController;
use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Auth\LogoutController;
use App\Http\Controllers\Api\V1\Auth\RefreshTokenController;
use App\Http\Controllers\Api\V1\Auth\RegisterController;
use App\Http\Controllers\Api\V1\DictionaryController;
use App\Http\Controllers\Api\V1\ExerciseController;
use App\Http\Controllers\Api\V1\UpdatePinController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\UserWordRepetitionController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::prefix('auth')->group(function (): void {
        Route::post('/register', RegisterController::class);
        Route::post('/login', LoginController::class);
        Route::post('/refresh', RefreshTokenController::class);
    });

    Route::middleware('access.token')->group(function (): void {
        Route::get('/app-updates/latest', AppUpdateController::class);
        Route::post('/auth/logout', LogoutController::class);
        Route::get('/users/me', [UserController::class, 'show']);
        Route::patch('/users/me', [UserController::class, 'update']);
        Route::put('/users/me/pin', UpdatePinController::class);
        Route::get('/dictionary', [DictionaryController::class, 'index']);
        Route::get('/dictionary/sync', [DictionaryController::class, 'sync']);
        Route::post(
            '/repetition-list/words',
            UserWordRepetitionController::class,
        );
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
