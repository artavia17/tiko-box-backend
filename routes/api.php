<?php

use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\AddressController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AvailabilityController;
use App\Http\Controllers\Api\AvatarController;
use App\Http\Controllers\Api\ConsolidationController;
use App\Http\Controllers\Api\LocationController;
use App\Http\Controllers\Api\LockerController;
use App\Http\Controllers\Api\PackageController;
use App\Http\Controllers\Api\PasswordResetController;
use App\Http\Controllers\Api\PaymentReportController;
use App\Http\Controllers\Api\PrealertController;
use App\Http\Controllers\Api\Staff\AdminController;
use App\Http\Controllers\Api\Staff\CatalogController;
use App\Http\Controllers\Api\Staff\ConsolidationController as StaffConsolidationController;
use App\Http\Controllers\Api\Staff\CustomerController;
use App\Http\Controllers\Api\Staff\FinanceController;
use App\Http\Controllers\Api\Staff\ManualInvoiceController;
use App\Http\Controllers\Api\Staff\PackageController as StaffPackageController;
use App\Http\Controllers\Api\Staff\PaymentReportController as StaffPaymentReportController;
use App\Http\Controllers\Api\Staff\SettingController;
use App\Http\Controllers\Api\Staff\StaffAuthController;
use App\Http\Controllers\Api\Staff\StaffPrealertController;
use App\Http\Controllers\Api\Staff\SupportController as StaffSupportController;
use App\Http\Controllers\Api\Staff\UserController as StaffUserController;
use App\Http\Controllers\Api\SupportController;
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

    // Los ajustes los lee todo el almacén; cambiarlos es de administración.
    Route::get('/settings', [SettingController::class, 'index']);

    // Paquetes
    Route::get('/summary', [StaffPackageController::class, 'summary']);
    Route::get('/prealerts', [StaffPrealertController::class, 'index']);
    Route::get('/catalog', [CatalogController::class, 'index']);
    Route::get('/packages', [StaffPackageController::class, 'index']);
    Route::post('/packages', [StaffPackageController::class, 'store']);

    // Cerrar un consolidado es trabajo de bodega: es quien sabe qué llegó y
    // cuánto pesa el bulto junto.
    Route::get('/consolidations', [StaffConsolidationController::class, 'index']);
    Route::post('/consolidations/{consolidation}/process', [StaffConsolidationController::class, 'process']);
    Route::patch('/packages/{package}/status', [StaffPackageController::class, 'updateStatus']);
    Route::post('/packages/{package}/deliver', [StaffPackageController::class, 'deliver']);
    // Varios paquetes del mismo cliente, con una firma y un solo cobro.
    Route::post('/packages/deliver-batch', [StaffPackageController::class, 'deliverBatch']);
    Route::patch('/packages/{package}/price', [StaffPackageController::class, 'adjustPrice']);
    Route::delete('/packages/{package}', [StaffPackageController::class, 'destroy']);
});

// Gestión del personal: solo administradores.
Route::middleware(['auth:sanctum', 'staff:admin'])->prefix('staff')->group(function () {
    Route::post('/catalog', [CatalogController::class, 'store']);
    Route::post('/catalog/import', [CatalogController::class, 'import']);
    Route::put('/catalog/{option}', [CatalogController::class, 'update']);
    Route::delete('/catalog/{option}', [CatalogController::class, 'destroy']);
    Route::get('/stats', [AdminController::class, 'stats']);
    Route::get('/today', [AdminController::class, 'today']);
    // Confirmar un pago es decidir que una factura quedó saldada: lo hace
    // un administrador, no cualquiera del almacén.
    Route::get('/payments', [StaffPaymentReportController::class, 'index']);
    Route::post('/packages/{package}/payments/{report}/verify', [StaffPaymentReportController::class, 'verify']);
    Route::post('/packages/{package}/payments/{report}/reject', [StaffPaymentReportController::class, 'reject']);

    // Soporte: la bandeja la atiende administración.
    Route::get('/support', [StaffSupportController::class, 'index']);
    Route::get('/support/{ticket}', [StaffSupportController::class, 'show']);
    Route::post('/support/{ticket}/claim', [StaffSupportController::class, 'claim']);
    Route::post('/support/{ticket}/messages', [StaffSupportController::class, 'reply']);
    Route::post('/support/{ticket}/close', [StaffSupportController::class, 'close']);
    Route::get('/support/{ticket}/attachments/{attachment}', [StaffSupportController::class, 'attachment']);

    Route::put('/settings', [SettingController::class, 'update']);

    // Facturas emitidas a mano, para paquetes que nunca se registraron.
    Route::get('/manual-invoices', [ManualInvoiceController::class, 'index']);
    Route::post('/manual-invoices', [ManualInvoiceController::class, 'store']);
    Route::put('/manual-invoices/{invoice}', [ManualInvoiceController::class, 'update']);
    Route::delete('/manual-invoices/{invoice}', [ManualInvoiceController::class, 'destroy']);

    Route::get('/finances', [FinanceController::class, 'index']);
    Route::patch('/finances/packages/{package}', [FinanceController::class, 'update']);
    Route::post('/finances/packages/{package}/void', [FinanceController::class, 'void']);
    Route::delete('/finances/packages/{package}/void', [FinanceController::class, 'restore']);
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

    // El catálogo de transportistas que administra el almacén, para que el
    // cliente elija de la misma lista y no de una quemada en la pantalla.
    Route::get('/couriers', [PrealertController::class, 'couriers']);

    // Consolidaciones del cliente.
    Route::get('/consolidations', [ConsolidationController::class, 'index']);
    Route::post('/consolidations', [ConsolidationController::class, 'store']);
    Route::get('/consolidations/{consolidation}', [ConsolidationController::class, 'show']);
    Route::delete('/consolidations/{consolidation}', [ConsolidationController::class, 'destroy']);
    Route::post('/consolidations/{consolidation}/prealerts', [ConsolidationController::class, 'addPrealert']);
    Route::delete('/consolidations/{consolidation}/prealerts/{prealert}', [ConsolidationController::class, 'removePrealert']);
    Route::post('/consolidations/{consolidation}/request', [ConsolidationController::class, 'request']);

    // Ajustes de la cuenta
    Route::patch('/me', [AccountController::class, 'update']);

    // Foto de perfil. Subir va por POST con multipart; PHP no arma $_FILES
    // en un PUT de verdad.
    Route::post('/me/avatar', [AvatarController::class, 'store']);
    Route::delete('/me/avatar', [AvatarController::class, 'destroy']);
    Route::get('/users/{user}/avatar', [AvatarController::class, 'show']);
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
    Route::get('/packages/{package}/photos/{photo}', [StaffPackageController::class, 'photo']);

    // Soporte del cliente: sus tickets y el hilo de cada uno.
    Route::get('/support', [SupportController::class, 'index']);
    Route::get('/support/unread', [SupportController::class, 'unread']);
    Route::post('/support', [SupportController::class, 'store']);
    Route::get('/support/{ticket}', [SupportController::class, 'show']);
    Route::post('/support/{ticket}/messages', [SupportController::class, 'reply']);
    Route::post('/support/{ticket}/close', [SupportController::class, 'close']);
    Route::get('/support/{ticket}/attachments/{attachment}', [SupportController::class, 'attachment']);

    // Con qué puede decir el cliente que pagó.
    Route::get('/payment-methods', [PaymentReportController::class, 'methods']);

    // Reportar el pago de una factura y volver a ver el comprobante.
    Route::post('/packages/{package}/payments', [PaymentReportController::class, 'store']);
    Route::get('/packages/{package}/payments/{report}/receipt', [PaymentReportController::class, 'receipt']);

    Route::get('/prealerts', [PrealertController::class, 'index']);
    Route::post('/prealerts', [PrealertController::class, 'store']);
    // La edición va por POST con _method=PUT: PHP no parsea multipart en PUT.
    Route::put('/prealerts/{prealert}', [PrealertController::class, 'update']);
    Route::get('/prealerts/{prealert}/invoice', [PrealertController::class, 'invoice']);
    Route::delete('/prealerts/{prealert}', [PrealertController::class, 'destroy']);
});
