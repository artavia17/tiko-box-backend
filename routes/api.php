<?php

use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\AddressController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AvailabilityController;
use App\Http\Controllers\Api\LocationController;
use App\Http\Controllers\Api\LockerController;
use App\Http\Controllers\Api\PasswordResetController;
use App\Http\Controllers\Api\PackageController;
use App\Http\Controllers\Api\PrealertController;
use App\Http\Controllers\Api\Staff\AdminController;
use App\Http\Controllers\Api\Staff\CustomerController;
use App\Http\Controllers\Api\Staff\UserController as StaffUserController;
use App\Http\Controllers\Api\Staff\PackageController as StaffPackageController;
use App\Http\Controllers\Api\Staff\StaffPrealertController;
use App\Http\Controllers\Api\Staff\StaffAuthController;
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

/*
|--------------------------------------------------------------------------
| App interna (almacén)
|--------------------------------------------------------------------------
*/

Route::post('/staff/login', [StaffAuthController::class, 'login']);

Route::middleware(['auth:sanctum', 'staff'])->prefix('staff')->group(function () {
    Route::get('/me', [StaffAuthController::class, 'me']);
    Route::post('/logout', [StaffAuthController::class, 'logout']);

    // Clientes
    Route::get('/customers', [CustomerController::class, 'index']);
    Route::get('/customers/{customer}', [CustomerController::class, 'show']);

    // Paquetes
    Route::get('/summary', [StaffPackageController::class, 'summary']);
    Route::get('/prealerts', [StaffPrealertController::class, 'index']);
    Route::get('/packages', [StaffPackageController::class, 'index']);
    Route::post('/packages', [StaffPackageController::class, 'store']);
    Route::patch('/packages/{package}/status', [StaffPackageController::class, 'updateStatus']);
    Route::post('/packages/{package}/deliver', [StaffPackageController::class, 'deliver']);
    Route::delete('/packages/{package}', [StaffPackageController::class, 'destroy']);
});

// Gestión del personal: solo administradores.
Route::middleware(['auth:sanctum', 'staff:admin'])->prefix('staff')->group(function () {
    Route::get('/stats', [AdminController::class, 'stats']);
    Route::get('/today', [AdminController::class, 'today']);
    Route::get('/users', [StaffUserController::class, 'index']);
    Route::post('/users', [StaffUserController::class, 'store']);
    Route::put('/users/{user}', [StaffUserController::class, 'update']);
});

/*
|--------------------------------------------------------------------------
| App de clientes
|--------------------------------------------------------------------------
*/

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
    Route::get('/packages', [PackageController::class, 'index']);
    Route::get('/packages/{package}', [PackageController::class, 'show']);
    Route::get('/packages/{package}/signature', [StaffPackageController::class, 'signature']);
    Route::get('/packages/{package}/photo', [StaffPackageController::class, 'photo']);

    Route::get('/prealerts', [PrealertController::class, 'index']);
    Route::post('/prealerts', [PrealertController::class, 'store']);
    // La edición va por POST con _method=PUT: PHP no parsea multipart en PUT.
    Route::put('/prealerts/{prealert}', [PrealertController::class, 'update']);
    Route::get('/prealerts/{prealert}/invoice', [PrealertController::class, 'invoice']);
    Route::delete('/prealerts/{prealert}', [PrealertController::class, 'destroy']);
});
