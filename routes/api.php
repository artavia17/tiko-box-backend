<?php

use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\AddressController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AvailabilityController;
use App\Http\Controllers\Api\LocationController;
use App\Http\Controllers\Api\LockerController;
use App\Http\Controllers\Api\PasswordResetController;
use App\Http\Controllers\Api\PrealertController;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => response()->json([
    'status' => 'ok',
    'service' => 'tikabox-api',
]));

// Registro y sesión
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/check-availability', [AvailabilityController::class, 'check']);
Route::post('/email/verify', [AuthController::class, 'verifyEmail']);
Route::post('/email/resend', [AuthController::class, 'resendCode']);

// Recuperación de contraseña
Route::post('/password/forgot', [PasswordResetController::class, 'forgot']);
Route::post('/password/reset', [PasswordResetController::class, 'reset']);

// Ubicaciones de Costa Rica para los selects
Route::get('/provinces', [LocationController::class, 'provinces']);
Route::get('/provinces/{province}/cantons', [LocationController::class, 'cantons']);
Route::get('/cantons/{canton}/districts', [LocationController::class, 'districts']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/locker', [LockerController::class, 'show']);

    // Ajustes de la cuenta
    Route::patch('/me', [AccountController::class, 'update']);
    Route::put('/me/password', [AccountController::class, 'updatePassword']);

    // Direcciones de entrega
    Route::get('/addresses', [AddressController::class, 'index']);
    Route::post('/addresses', [AddressController::class, 'store']);
    Route::put('/addresses/{address}', [AddressController::class, 'update']);
    Route::post('/addresses/{address}/default', [AddressController::class, 'makeDefault']);
    Route::delete('/addresses/{address}', [AddressController::class, 'destroy']);

    // Prealertas
    Route::get('/prealerts', [PrealertController::class, 'index']);
    Route::post('/prealerts', [PrealertController::class, 'store']);
    Route::delete('/prealerts/{prealert}', [PrealertController::class, 'destroy']);
});
