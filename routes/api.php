<?php

use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Auth\LogoutController;
use App\Http\Controllers\Api\V1\Auth\RefreshTokenController;
use App\Http\Controllers\Api\V1\Auth\RegisterController;
use App\Http\Controllers\Api\V1\UpdatePinController;
use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::prefix('auth')->group(function (): void {
        Route::post('/register', RegisterController::class);
        Route::post('/login', LoginController::class);
        Route::post('/refresh', RefreshTokenController::class);
    });

    Route::middleware('access.token')->group(function (): void {
        Route::post('/auth/logout', LogoutController::class);
        Route::get('/users/me', [UserController::class, 'show']);
        Route::patch('/users/me', [UserController::class, 'update']);
        Route::put('/users/me/pin', UpdatePinController::class);
    });
});
