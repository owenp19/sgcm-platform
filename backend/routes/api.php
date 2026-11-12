<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AnnouncementController;
use App\Http\Controllers\Api\AppointmentController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CertificateController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\DeviceController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\DocumentAdminController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ProcedureController;
use App\Http\Controllers\Api\RequestController;
use App\Http\Controllers\Api\TourismCardController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - contrato idéntico al backend Node/Express
|--------------------------------------------------------------------------
*/

// Health check (usado por el frontend para verificar conectividad).
// JSON para la app (Accept: application/json) y pagina HTML para navegadores.
Route::get('/health', [HealthController::class, 'health'])->middleware('throttle:api');

// Auth
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:auth');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:auth');
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:auth');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:auth');

    Route::middleware(['auth:api', 'jwt.fresh', 'throttle:api'])->group(function () {
        Route::post('/refresh', [AuthController::class, 'refresh']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/profile', [AuthController::class, 'getProfile']);
        Route::put('/profile', [AuthController::class, 'updateProfile']);
        Route::put('/photo', [AuthController::class, 'uploadPhoto']);
        Route::put('/change-password', [AuthController::class, 'changePassword']);
        Route::put('/change-email', [AuthController::class, 'changeEmail']);
        Route::get('/users', [AuthController::class, 'listUsers'])->middleware('role:admin,funcionario');
        Route::put('/users/{userId}/roles', [AuthController::class, 'updateUserRoles'])->middleware('role:admin,funcionario');
        Route::delete('/users/{userId}', [AuthController::class, 'deleteUser'])->middleware('role:admin');
        Route::get('/biometric-preference', [AuthController::class, 'getBiometricPreference']);
        Route::put('/biometric-preference', [AuthController::class, 'setBiometricPreference']);
    });
});

// Notifications
Route::prefix('notifications')->middleware(['auth:api', 'jwt.fresh', 'throttle:api'])->group(function () {
    Route::get('/', [NotificationController::class, 'getNotifications']);
    Route::get('/unread-count', [NotificationController::class, 'getUnreadCount']);
    Route::patch('/read-all', [NotificationController::class, 'markAllAsRead']);
    Route::patch('/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::delete('/clear-all', [NotificationController::class, 'clearAll']);
    Route::post('/seed', [NotificationController::class, 'seedNotifications']);
});

// Procedures (trámites)
Route::prefix('procedures')->group(function () {
    Route::get('/', [ProcedureController::class, 'getAll']);
    Route::get('/{slug}', [ProcedureController::class, 'getBySlug']);

    Route::middleware(['auth:api', 'jwt.fresh', 'role:admin'])->group(function () {
        Route::post('/', [ProcedureController::class, 'create']);
        Route::put('/{id}', [ProcedureController::class, 'update']);
        Route::delete('/{id}', [ProcedureController::class, 'remove']);
    });
});

// Requests (radicados)
Route::prefix('requests')->group(function () {
    Route::post('/search-record', [RequestController::class, 'searchRecord'])->middleware('throttle:api');

    Route::middleware(['auth:api', 'jwt.fresh', 'throttle:api'])->group(function () {
        Route::post('/', [RequestController::class, 'create']);
        Route::get('/my', [RequestController::class, 'getMyRequests']);
        Route::get('/my-procedures', [RequestController::class, 'getMyProcedures']);
        Route::get('/my-procedures/{id}', [RequestController::class, 'getProcedureDetail']);
        Route::get('/tracking/{trackingNumber}', [RequestController::class, 'getByTrackingNumber']);
        Route::get('/assigned', [RequestController::class, 'getMyAssignedRequests'])->middleware('role:funcionario,admin');
        Route::get('/all', [RequestController::class, 'getAllRequests'])->middleware('role:funcionario,admin');
        Route::patch('/{id}/status', [RequestController::class, 'updateStatus'])->middleware('role:funcionario,admin');
        Route::patch('/{id}/assign', [RequestController::class, 'assignRequest'])->middleware('role:admin');
    });
});

// Documents
Route::prefix('documents')->middleware(['auth:api', 'jwt.fresh', 'throttle:api'])->group(function () {
    Route::post('/upload', [DocumentController::class, 'upload']);
    Route::get('/{id}/download', [DocumentController::class, 'download']);
    Route::patch('/{id}/validate', [DocumentController::class, 'validateDocument'])->middleware('role:funcionario,admin');
    Route::delete('/{id}', [DocumentController::class, 'delete']);
});

// Admin
Route::prefix('admin')->group(function () {
    Route::middleware(['auth:api', 'jwt.fresh', 'role:admin,funcionario', 'throttle:api'])->group(function () {
        Route::get('/dashboard', [AdminController::class, 'getDashboardStats']);
        Route::get('/reports', [AdminController::class, 'getReports']);
        Route::get('/export/excel', [AdminController::class, 'exportExcel']);
        Route::get('/export/pdf', [AdminController::class, 'exportPdf']);
        Route::get('/funcionarios', [AdminController::class, 'getFuncionarios']);
        Route::get('/population-map', [AdminController::class, 'getPopulationMap']);
        Route::get('/documents', [DocumentAdminController::class, 'getAllDocuments']);
        Route::get('/documents/stats', [DocumentAdminController::class, 'getAllDocumentsStats']);
    });

    Route::get('/audit-log', [AdminController::class, 'getAuditLog'])
        ->middleware(['auth:api', 'jwt.fresh', 'role:admin', 'throttle:api']);
});

// Appointments (citas)
Route::prefix('appointments')->group(function () {
    Route::get('/services', [AppointmentController::class, 'getServices']);
    Route::get('/offices', [AppointmentController::class, 'getOffices']);
    Route::get('/availability', [AppointmentController::class, 'getAvailability'])->middleware('throttle:api');

    Route::middleware(['auth:api', 'jwt.fresh', 'throttle:api'])->group(function () {
        Route::post('/', [AppointmentController::class, 'create']);
        Route::get('/my', [AppointmentController::class, 'getMyAppointments']);
        Route::get('/all', [AppointmentController::class, 'getAllAppointments'])->middleware('role:admin,funcionario');
        Route::patch('/{id}/cancel', [AppointmentController::class, 'cancel']);
        Route::get('/{code}', [AppointmentController::class, 'getAppointmentByCode']);
    });
});

// Devices (push tokens)
Route::prefix('devices')->middleware(['auth:api', 'jwt.fresh', 'throttle:api'])->group(function () {
    Route::post('/register', [DeviceController::class, 'register']);
    Route::post('/unregister', [DeviceController::class, 'unregister']);
    Route::get('/my', [DeviceController::class, 'myDevices']);
});

// Announcements
Route::prefix('announcements')->group(function () {
    Route::get('/active', [AnnouncementController::class, 'getActive']);

    Route::middleware(['auth:api', 'jwt.fresh', 'role:admin'])->group(function () {
        Route::get('/', [AnnouncementController::class, 'getAll']);
        Route::post('/', [AnnouncementController::class, 'create']);
        Route::put('/{id}', [AnnouncementController::class, 'update']);
        Route::delete('/{id}', [AnnouncementController::class, 'remove']);
    });
});

// Certificates (constancias PDF+QR)
Route::prefix('certificates')->group(function () {
    Route::post('/validate-qr', [CertificateController::class, 'validateQr'])->middleware('throttle:api');
    Route::get('/generate/{trackingNumber}', [CertificateController::class, 'generate'])
        ->middleware(['auth:api', 'jwt.fresh']);
});

// Tourism card (tarjeta de turismo)
Route::prefix('tourism-card')->group(function () {
    Route::get('/tariff', [TourismCardController::class, 'getTariff']);
    Route::post('/quote', [TourismCardController::class, 'quote'])->middleware('throttle:tourism-card');
    Route::post('/card', [TourismCardController::class, 'create'])->middleware('throttle:tourism-card');
    Route::post('/card/{code}/payment/init', [TourismCardController::class, 'initPayment'])->middleware('throttle:tourism-card');
    Route::get('/card/{code}/payment/status', [TourismCardController::class, 'checkPaymentStatus'])->middleware('throttle:api');
    Route::get('/card/{code}/receipt', [TourismCardController::class, 'getReceipt'])->middleware('throttle:tourism-card');
    Route::get('/card/{code}/verify', [TourismCardController::class, 'verifyCard'])->middleware('throttle:api');
    Route::post('/card/{code}/share-location', [TourismCardController::class, 'shareLocation'])->middleware('throttle:tourism-card');
    Route::get('/card/verify/qr/{qr_token}', [TourismCardController::class, 'verifyByQrToken'])->middleware('throttle:api');
    Route::post('/search', [TourismCardController::class, 'searchCard'])->middleware('throttle:tourism-card');
    Route::post('/payment/webhook', [TourismCardController::class, 'paymentWebhook'])
        ->middleware(\App\Http\Middleware\VerifyPaymentSignature::class);
    Route::post('/expired/check', [TourismCardController::class, 'updateCardStatusExpired'])
        ->middleware(['auth:api', 'jwt.fresh']);
});

// Chat (Miss Lorna)
Route::post('/chat', [ChatController::class, 'chat'])
    ->middleware(['auth:api', 'jwt.fresh', 'throttle:chat']);
