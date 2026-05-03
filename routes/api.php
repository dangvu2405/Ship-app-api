<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Toàn bộ nghiệp vụ dùng một prefix `/api/...` (không mirror `/api/v1`).
|
*/

$healthResponse = static function () {
    return response()->json([
        'success' => true,
        'message' => 'API is running',
        'timestamp' => now()->toDateTimeString(),
    ]);
};

$registerPublicAuthRoutes = static function (): void {
    Route::prefix('auth')->group(function (): void {
        Route::post('/login', [AuthController::class, 'login'])
            ->middleware('throttle:5,1');
        Route::post('/social/login', [AuthController::class, 'socialLogin'])
            ->middleware('throttle:10,1');
        Route::post('/refresh-token', [AuthController::class, 'refreshByToken'])
            ->middleware('throttle:20,1');
        Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])
            ->middleware('throttle:3,1');
        Route::post('/check-otp', [AuthController::class, 'checkOtp'])
            ->middleware('throttle:10,1');
        Route::post('/reset-password', [AuthController::class, 'resetPassword'])
            ->middleware('throttle:5,1');
    });
};

$registerAuthenticatedRoutes = static function (): void {
    Route::prefix('auth')->group(function (): void {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
        Route::get('/logs', [AuthController::class, 'logs']);
        Route::get('/actions', [AuthController::class, 'actions']);
        Route::get('/sessions', [AuthController::class, 'sessions']);
        Route::get('/sessions/summary', [AuthController::class, 'sessionsSummary']);
        Route::post('/sessions/{sessionId}/revoke', [AuthController::class, 'revokeSession']);
        Route::post('/sessions/{sessionId}/lock-account', [AuthController::class, 'lockAccountForSession']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::patch('/password', [AuthController::class, 'changePassword']);
    });

    Route::post('/upload', [\App\Http\Controllers\Api\UploadController::class, 'store']);
    Route::post('/upload/image', [\App\Http\Controllers\Api\UploadController::class, 'storeImage']);
    Route::post('/upload/document', [\App\Http\Controllers\Api\UploadController::class, 'storeDocument']);
    Route::get('payrolls/my-salary', [\App\Http\Controllers\Api\PayrollController::class, 'mySalary']);

    Route::prefix('chat')->group(function (): void {
        Route::get('/sessions', [\App\Http\Controllers\Api\ChatController::class, 'sessions']);
        Route::delete('/sessions/{sessionId}', [\App\Http\Controllers\Api\ChatController::class, 'destroySession']);
        Route::get('/messages', [\App\Http\Controllers\Api\ChatController::class, 'index']);
        Route::post('/messages', [\App\Http\Controllers\Api\ChatController::class, 'store']);
        Route::post('/messages/stream', [\App\Http\Controllers\Api\ChatController::class, 'stream']);
    });

    Route::prefix('notifications')->group(function (): void {
        Route::get('/unread-count', [\App\Http\Controllers\Api\NotificationController::class, 'unreadCount']);
        Route::post('/read-all', [\App\Http\Controllers\Api\NotificationController::class, 'markAllRead']);
        Route::patch('/read-all', [\App\Http\Controllers\Api\NotificationController::class, 'markAllReadPatch']);
        Route::post('/{id}/read', [\App\Http\Controllers\Api\NotificationController::class, 'markRead'])
            ->where('id', '[0-9a-fA-F\\-]{36}');
        Route::patch('/{id}/read', [\App\Http\Controllers\Api\NotificationController::class, 'markRead'])
            ->where('id', '[0-9a-fA-F\\-]{36}');
        Route::delete('/{id}', [\App\Http\Controllers\Api\NotificationController::class, 'destroy'])
            ->where('id', '[0-9a-fA-F\\-]{36}');
        Route::get('/', [\App\Http\Controllers\Api\NotificationController::class, 'index']);
    });

    Route::prefix('workforce')->group(function (): void {
        Route::get('driver-schedules', [\App\Http\Controllers\Api\WorkforceController::class, 'schedules']);
        Route::get('leave-requests', [\App\Http\Controllers\Api\WorkforceController::class, 'leaveRequests']);
        Route::get('absences', [\App\Http\Controllers\Api\WorkforceController::class, 'absences']);

        Route::middleware('permission:schedule.approve')->group(function (): void {
            Route::put('driver-schedules/{id}/approve', [\App\Http\Controllers\Api\WorkforceController::class, 'approveSchedule']);
            Route::put('driver-schedules/{id}/lock', [\App\Http\Controllers\Api\WorkforceController::class, 'lockSchedule']);
        });
    });

    Route::get('public-holidays', [\App\Http\Controllers\Api\PublicHolidayController::class, 'index']);
};

$cetaOffice = require __DIR__.'/ceta_office.php';
$cetaCompany = require __DIR__.'/ceta_company.php';
$cetaPlatform = require __DIR__.'/ceta_platform.php';

$mAuth = ['auth:sanctum', 'tenant.context', 'track.actions'];
$mOffice = array_merge($mAuth, ['role:office_admin']);
$mCompany = array_merge($mAuth, ['role:company_admin']);
$mAdmin = array_merge($mAuth, ['role:admin']);

Route::get('/', function () {
    return response()->json([
        'success' => true,
        'message' => 'Company Ship API',
        'version' => 'api',
        'preferred_base' => url('/api'),
    ]);
});
Route::get('/health', $healthResponse);

$registerPublicAuthRoutes();

Route::middleware($mAuth)->group($registerAuthenticatedRoutes);

Route::middleware($mOffice)->group($cetaOffice);

Route::middleware($mCompany)->group($cetaCompany);

Route::middleware($mAdmin)->group($cetaPlatform);
