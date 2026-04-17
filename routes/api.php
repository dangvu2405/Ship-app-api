<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Shared response handlers
$healthResponse = static function () {
    return response()->json([
        'success' => true,
        'message' => 'API is running',
        'timestamp' => now()->toDateTimeString(),
    ]);
};

$currentUserResponse = static function (Request $request) {
    $user = $request->user();
    $user->load(['driver', 'roles.permissions']);

    return response()->json([
        'success' => true,
        'message' => 'OK',
        'data' => [
            'user' => $user,
        ],
    ]);
};

$registerAuthenticatedRoutes = static function () use ($currentUserResponse): void {
    Route::prefix('auth')->group(function () use ($currentUserResponse): void {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
        Route::get('/logs', [AuthController::class, 'logs']);
        Route::get('/actions', [AuthController::class, 'actions']);
        Route::get('/sessions', [AuthController::class, 'sessions']);
        Route::get('/sessions/summary', [AuthController::class, 'sessionsSummary']);
        Route::post('/sessions/{sessionId}/revoke', [AuthController::class, 'revokeSession']);
        Route::post('/sessions/{sessionId}/lock-account', [AuthController::class, 'lockAccountForSession']);
        Route::get('/me', $currentUserResponse);
    });

    Route::get('/user', $currentUserResponse);
    Route::get('payrolls/my-salary', [\App\Http\Controllers\Api\PayrollController::class, 'mySalary']);

    Route::prefix('chat')->group(function (): void {
        Route::get('/sessions', [\App\Http\Controllers\Api\ChatController::class, 'sessions']);
        Route::delete('/sessions/{sessionId}', [\App\Http\Controllers\Api\ChatController::class, 'destroySession']);
        Route::get('/messages', [\App\Http\Controllers\Api\ChatController::class, 'index']);
        Route::post('/messages', [\App\Http\Controllers\Api\ChatController::class, 'store']);
        Route::post('/messages/stream', [\App\Http\Controllers\Api\ChatController::class, 'stream']);
    });

    Route::prefix('workforce')->group(function (): void {
        Route::get('driver-schedules', [\App\Http\Controllers\Api\WorkforceController::class, 'schedules']);
        Route::put('driver-schedules/{id}/approve', [\App\Http\Controllers\Api\WorkforceController::class, 'approveSchedule']);
        Route::put('driver-schedules/{id}/lock', [\App\Http\Controllers\Api\WorkforceController::class, 'lockSchedule']);
        Route::get('leave-requests', [\App\Http\Controllers\Api\WorkforceController::class, 'leaveRequests']);
        Route::get('absences', [\App\Http\Controllers\Api\WorkforceController::class, 'absences']);
    });

    Route::get('public-holidays', [\App\Http\Controllers\Api\PublicHolidayController::class, 'index']);
};

$registerAdminRoutes = static function (): void {
    Route::prefix('auth')->group(function (): void {
        Route::post('/register', [AuthController::class, 'register']);
    });

    Route::apiResource('companies', \App\Http\Controllers\Api\CompanyController::class);
    Route::apiResource('offices', \App\Http\Controllers\Api\OfficeController::class);
    Route::apiResource('departments', \App\Http\Controllers\Api\DepartmentController::class);
    Route::apiResource('positions', \App\Http\Controllers\Api\PositionController::class);
    Route::apiResource('drivers', \App\Http\Controllers\Api\DriverController::class);
    Route::apiResource('vehicles', \App\Http\Controllers\Api\VehicleController::class);
    Route::apiResource('vehicle_assignments', \App\Http\Controllers\Api\VehicleAssignmentController::class);
    Route::apiResource('vehicle_expenses', \App\Http\Controllers\Api\VehicleExpenseController::class);
    Route::apiResource('customers', \App\Http\Controllers\Api\CustomerController::class);
    Route::apiResource('trips', \App\Http\Controllers\Api\TripController::class);
    Route::apiResource('trip_bonus_rules', \App\Http\Controllers\Api\TripBonusRuleController::class);
    Route::apiResource('invoices', \App\Http\Controllers\Api\InvoiceController::class);

    // Payroll
    Route::post('payrolls/{id}/approve', [\App\Http\Controllers\Api\PayrollController::class, 'approve'])->name('payrolls.approve');
    Route::post('payrolls/{id}/lock', [\App\Http\Controllers\Api\PayrollController::class, 'lock'])->name('payrolls.lock');
    Route::post('payrolls/{id}/mark-paid', [\App\Http\Controllers\Api\PayrollController::class, 'markPaid'])->name('payrolls.mark-paid');
    Route::get('payrolls/{id}/export', [\App\Http\Controllers\Api\PayrollController::class, 'export'])->name('payrolls.export');
    Route::get('payrolls/driver/{driverId}', [\App\Http\Controllers\Api\PayrollController::class, 'driverMonthlySalary'])->name('payrolls.driver-monthly');
    Route::apiResource('payrolls', \App\Http\Controllers\Api\PayrollController::class);

    // Driver Work Schedules
    Route::post('driver-schedules/{driverWorkSchedule}/submit', [\App\Http\Controllers\Api\DriverScheduleController::class, 'submit'])->name('driver-schedules.submit');
    Route::post('driver-schedules/{driverWorkSchedule}/approve', [\App\Http\Controllers\Api\DriverScheduleController::class, 'approve'])->name('driver-schedules.approve');
    Route::post('driver-schedules/{driverWorkSchedule}/reject', [\App\Http\Controllers\Api\DriverScheduleController::class, 'reject'])->name('driver-schedules.reject');
    Route::post('driver-schedules/{driverWorkSchedule}/lock', [\App\Http\Controllers\Api\DriverScheduleController::class, 'lock'])->name('driver-schedules.lock');
    Route::post('driver-schedules/{driverWorkSchedule}/override', [\App\Http\Controllers\Api\DriverScheduleController::class, 'override'])->name('driver-schedules.override');
    Route::get('driver-schedules/{driverWorkSchedule}/hos-check', [\App\Http\Controllers\Api\DriverScheduleController::class, 'hosCheck'])->name('driver-schedules.hos-check');
    Route::post('driver-schedules/{driverWorkSchedule}/hos-check', [\App\Http\Controllers\Api\DriverScheduleController::class, 'hosCheck'])->name('driver-schedules.hos-check.post');
    Route::apiResource('driver-schedules', \App\Http\Controllers\Api\DriverScheduleController::class);

    // Attendance
    Route::post('attendance/check-in', [\App\Http\Controllers\Api\AttendanceController::class, 'checkIn'])->name('attendance.check-in');
    Route::post('attendance/check-out', [\App\Http\Controllers\Api\AttendanceController::class, 'checkOut'])->name('attendance.check-out');
    Route::patch('attendance/{id}/adjust', [\App\Http\Controllers\Api\AttendanceController::class, 'adjust'])->name('attendance.adjust');
    Route::get('attendance', [\App\Http\Controllers\Api\AttendanceController::class, 'index'])->name('attendance.index');
    // Legacy aliases for FE compatibility
    Route::get('attendances', [\App\Http\Controllers\Api\AttendanceController::class, 'index'])->name('attendances.index');
    Route::post('attendances/check-in', [\App\Http\Controllers\Api\AttendanceController::class, 'checkIn'])->name('attendances.check-in');
    Route::post('attendances/check-out', [\App\Http\Controllers\Api\AttendanceController::class, 'checkOut'])->name('attendances.check-out');
    Route::patch('attendances/{id}/adjust', [\App\Http\Controllers\Api\AttendanceController::class, 'adjust'])->name('attendances.adjust');
    Route::get('attendances/late', [\App\Http\Controllers\Api\AttendanceController::class, 'late'])->name('attendances.late');
    Route::get('attendances/late/list', [\App\Http\Controllers\Api\AttendanceController::class, 'late'])->name('attendances.late.list');
    Route::post('attendances/late/notify', [\App\Http\Controllers\Api\AttendanceController::class, 'notifyLate'])->name('attendances.late.notify');

    // Leave
    Route::get('leave/types', [\App\Http\Controllers\Api\LeaveController::class, 'types'])->name('leave.types');
    Route::post('leave/{leaveRequest}/approve', [\App\Http\Controllers\Api\LeaveController::class, 'approve'])->name('leave.approve');
    Route::post('leave/{leaveRequest}/reject', [\App\Http\Controllers\Api\LeaveController::class, 'reject'])->name('leave.reject');
    Route::post('leave/{leaveRequest}/cancel', [\App\Http\Controllers\Api\LeaveController::class, 'cancel'])->name('leave.cancel');
    Route::apiResource('leave', \App\Http\Controllers\Api\LeaveController::class)->only(['index', 'store', 'show']);

    // Overtime
    Route::post('overtime/{overtimeRequest}/approve', [\App\Http\Controllers\Api\OvertimeController::class, 'approve'])->name('overtime.approve');
    Route::post('overtime/{overtimeRequest}/reject', [\App\Http\Controllers\Api\OvertimeController::class, 'reject'])->name('overtime.reject');
    Route::apiResource('overtime', \App\Http\Controllers\Api\OvertimeController::class)->only(['index', 'store', 'show']);

    // Violations
    Route::post('violations/{violation}/confirm', [\App\Http\Controllers\Api\ViolationController::class, 'confirm'])->name('violations.confirm');
    Route::post('violations/{violation}/dispute', [\App\Http\Controllers\Api\ViolationController::class, 'dispute'])->name('violations.dispute');
    Route::post('violations/{violation}/resolve-dispute', [\App\Http\Controllers\Api\ViolationController::class, 'resolveDispute'])->name('violations.resolve-dispute');
    Route::post('violations/{violation}/waive', [\App\Http\Controllers\Api\ViolationController::class, 'waive'])->name('violations.waive');
    Route::apiResource('violations', \App\Http\Controllers\Api\ViolationController::class)->only(['index', 'store', 'show']);

    Route::apiResource('users', \App\Http\Controllers\Api\UserController::class);
    Route::post('roles/{role}/permissions', [\App\Http\Controllers\Api\RoleController::class, 'syncPermissions'])->name('roles.permissions');
    Route::apiResource('roles', \App\Http\Controllers\Api\RoleController::class);
    Route::get('permissions', [\App\Http\Controllers\Api\PermissionController::class, 'index']);
    Route::get('permissions/{permission}', [\App\Http\Controllers\Api\PermissionController::class, 'show']);

    Route::get('reports/dashboard', [\App\Http\Controllers\Api\ReportsController::class, 'dashboard']);
    Route::get('reports/payroll-summary', [\App\Http\Controllers\Api\ReportsController::class, 'payrollSummary']);
    Route::get('reports/revenue-summary', [\App\Http\Controllers\Api\ReportsController::class, 'revenueSummary']);
    Route::post('ai/business-assist', [\App\Http\Controllers\Api\AiAdvisorController::class, 'businessAssist']);

    // Legacy compatibility routes
    Route::get('documentation', static function () {
        return response()->json([
            'success' => true,
            'message' => 'API documentation endpoint',
            'data' => [
                'swagger_ui' => url('/api/documentation'),
                'openapi_json' => url('/docs?format=openapi'),
            ],
        ]);
    });

    Route::get('employees', static function (\Illuminate\Http\Request $request) {
        $companyId = app(\App\Tenancy\TenantContext::class)->getCompanyId();

        if ($companyId === null) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $drivers = \App\Models\Driver::withoutGlobalScope('tenant')
            ->where('company_id', $companyId)
            ->paginate(20);

        return response()->json([
            'success' => true,
            'message' => 'Legacy alias: employees mapped to drivers',
            'data'    => $drivers,
        ]);
    });

    Route::get('allowances', static function () {
        return response()->json([
            'success' => true,
            'message' => 'Legacy endpoint retained for compatibility',
            'data' => [],
        ]);
    });

    Route::get('deductions', static function () {
        return response()->json([
            'success' => true,
            'message' => 'Legacy endpoint retained for compatibility',
            'data' => [],
        ]);
    });
};

// Public routes
Route::get('/', function () {
    return response()->json([
        'success' => true,
        'message' => 'Company Ship API',
        'version' => 'v1',
    ]);
});
Route::get('/health', $healthResponse);

// Authentication routes (legacy public)
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:5,1');
    Route::post('/social/login', [AuthController::class, 'socialLogin'])
        ->middleware('throttle:10,1');
    Route::post('/refresh-token', [AuthController::class, 'refreshByToken'])
        ->middleware('throttle:20,1');
});

$larkWebhookController = 'App\\Http\\Controllers\\Api\\LarkWebhookController';
$larkAuthController = 'App\\Http\\Controllers\\Api\\LarkAuthController';

if (class_exists($larkWebhookController)) {
    // Lark webhook/event entrypoint
    Route::post('/lark/webhook', [$larkWebhookController, 'handle']);
}

// Protected routes: authenticated users (legacy)
Route::middleware(['auth:sanctum', 'tenant.context', 'track.actions'])->group($registerAuthenticatedRoutes);

// Protected routes: admin only (legacy)
Route::middleware(['auth:sanctum', 'tenant.context', 'track.actions', 'role:admin'])->group($registerAdminRoutes);

// Versioned API routes
Route::prefix('v1')->group(function () use ($healthResponse, $registerAuthenticatedRoutes, $registerAdminRoutes, $larkWebhookController, $larkAuthController): void {
    Route::get('/health', $healthResponse);

    if (class_exists($larkWebhookController)) {
        // Versioned alias for Lark webhook/event entrypoint.
        Route::post('/lark/webhook', [$larkWebhookController, 'handle']);
    }

    if (class_exists($larkAuthController)) {
        // Lark OAuth login flow (API-first)
        Route::prefix('lark/oauth')->group(function () use ($larkAuthController): void {
            Route::get('/redirect', [$larkAuthController, 'redirect']);
            Route::match(['get', 'post'], '/callback', [$larkAuthController, 'callback']);
        });
    }

    Route::prefix('auth')->group(function (): void {
        Route::post('/login', [AuthController::class, 'login'])
            ->middleware('throttle:5,1');
        Route::post('/social/login', [AuthController::class, 'socialLogin'])
            ->middleware('throttle:10,1');
        Route::post('/refresh-token', [AuthController::class, 'refreshByToken'])
            ->middleware('throttle:20,1');
        Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])
            ->middleware('throttle:3,1');
        Route::post('/reset-password', [AuthController::class, 'resetPassword'])
            ->middleware('throttle:5,1');
    });

    Route::middleware(['auth:sanctum', 'tenant.context', 'track.actions'])->group($registerAuthenticatedRoutes);
    Route::middleware(['auth:sanctum', 'tenant.context', 'track.actions', 'role:admin'])->group($registerAdminRoutes);
});

if (class_exists($larkAuthController)) {
    // Backward-compatible OAuth callback alias
    Route::match(['get', 'post'], '/callback/lark', [$larkAuthController, 'callback']);
}
