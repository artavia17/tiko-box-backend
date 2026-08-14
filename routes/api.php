<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\LocationController;
use App\Http\Controllers\Api\LockerController;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => response()->json([
    'status' => 'ok',
    'service' => 'tikabox-api',
]));

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/email/verify', [AuthController::class, 'verifyEmail']);
Route::post('/email/resend', [AuthController::class, 'resendCode']);

// Ubicaciones de Costa Rica para los selects del registro.
Route::get('/provinces', [LocationController::class, 'provinces']);
Route::get('/provinces/{province}/cantons', [LocationController::class, 'cantons']);
Route::get('/cantons/{canton}/districts', [LocationController::class, 'districts']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/locker', [LockerController::class, 'show']);
});
