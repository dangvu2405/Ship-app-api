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
            'tenants' => $user->resolveTenants(),
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
    Route::post('/upload', [\App\Http\Controllers\Api\UploadController::class, 'store']);
    Route::get('payrolls/my-salary', [\App\Http\Controllers\Api\PayrollController::class, 'mySalary']);

    Route::prefix('chat')->group(function (): void {
        Route::get('/sessions', [\App\Http\Controllers\Api\ChatController::class, 'sessions']);
        Route::delete('/sessions/{sessionId}', [\App\Http\Controllers\Api\ChatController::class, 'destroySession']);
        Route::get('/messages', [\App\Http\Controllers\Api\ChatController::class, 'index']);
        Route::post('/messages', [\App\Http\Controllers\Api\ChatController::class, 'store']);
        Route::post('/messages/stream', [\App\Http\Controllers\Api\ChatController::class, 'stream']);
    });

    // Database notifications (Laravel `notifications` table) — any authenticated user
    Route::prefix('notifications')->group(function (): void {
        Route::get('/unread-count', [\App\Http\Controllers\Api\NotificationController::class, 'unreadCount']);
        Route::post('/read-all', [\App\Http\Controllers\Api\NotificationController::class, 'markAllRead']);
        Route::post('/{id}/read', [\App\Http\Controllers\Api\NotificationController::class, 'markRead'])
            ->where('id', '[0-9a-fA-F\\-]{36}');
        Route::get('/', [\App\Http\Controllers\Api\NotificationController::class, 'index']);
    });

    Route::prefix('workforce')->group(function (): void {
        // View-only: mọi user đã xác thực
        Route::get('driver-schedules', [\App\Http\Controllers\Api\WorkforceController::class, 'schedules']);
        Route::get('leave-requests', [\App\Http\Controllers\Api\WorkforceController::class, 'leaveRequests']);
        Route::get('absences', [\App\Http\Controllers\Api\WorkforceController::class, 'absences']);

        // Mutating actions: yêu cầu quyền duyệt lịch (admin hoặc manager)
        Route::middleware('permission:schedule.approve')->group(function (): void {
            Route::put('driver-schedules/{id}/approve', [\App\Http\Controllers\Api\WorkforceController::class, 'approveSchedule']);
            Route::put('driver-schedules/{id}/lock', [\App\Http\Controllers\Api\WorkforceController::class, 'lockSchedule']);
        });
    });

    Route::get('public-holidays', [\App\Http\Controllers\Api\PublicHolidayController::class, 'index']);
};

$registerAdminRoutes = static function (): void {
    // =========================
    // Authentication
    // =========================
    Route::prefix('auth')->group(function (): void {
        Route::post('/register', [AuthController::class, 'register']);
    });

    // =========================
    // Master Data
    // =========================
    Route::apiResource('companies', \App\Http\Controllers\Api\Company\CompanyController::class);
    Route::apiResource('work-schedule-templates', \App\Http\Controllers\Api\WorkScheduleTemplateController::class)
        ->except(['create', 'edit']);
    Route::post('offices/{office}/apply-schedule', [\App\Http\Controllers\Api\OfficeApplyScheduleController::class, 'store']);
    Route::apiResource('offices', \App\Http\Controllers\Api\OfficeController::class);
    Route::apiResource('departments', \App\Http\Controllers\Api\DepartmentController::class);
    Route::apiResource('positions', \App\Http\Controllers\Api\PositionController::class);
    Route::apiResource('drivers', \App\Http\Controllers\Api\DriverController::class);
    Route::apiResource('vehicles', \App\Http\Controllers\Api\VehicleController::class);
    Route::apiResource('vehicle_assignments', \App\Http\Controllers\Api\VehicleAssignmentController::class);
    Route::apiResource('vehicle_expenses', \App\Http\Controllers\Api\VehicleExpenseController::class);
    Route::apiResource('customers', \App\Http\Controllers\Api\CustomerController::class);

    // =========================
    // Trips
    // =========================
    Route::prefix('trips')->group(function (): void {
        Route::post('{id}/assign', [\App\Http\Controllers\Api\TripController::class, 'assign'])->name('trips.assign');
        Route::post('{id}/start', [\App\Http\Controllers\Api\TripController::class, 'start'])->name('trips.start');
        Route::post('{id}/pickup', [\App\Http\Controllers\Api\TripController::class, 'pickup'])->name('trips.pickup');
        Route::post('{id}/transit', [\App\Http\Controllers\Api\TripController::class, 'transit'])->name('trips.transit');
        Route::post('{id}/arrive', [\App\Http\Controllers\Api\TripController::class, 'arrive'])->name('trips.arrive');
        Route::post('{id}/complete', [\App\Http\Controllers\Api\TripController::class, 'complete'])->name('trips.complete');
        Route::post('{id}/cancel', [\App\Http\Controllers\Api\TripController::class, 'cancel'])->name('trips.cancel');
        Route::post('{id}/delay', [\App\Http\Controllers\Api\TripController::class, 'delay'])->name('trips.delay');
        Route::post('{id}/resume', [\App\Http\Controllers\Api\TripController::class, 'resume'])->name('trips.resume');
    });
    Route::apiResource('trips', \App\Http\Controllers\Api\TripController::class);
    Route::apiResource('trip_bonus_rules', \App\Http\Controllers\Api\TripBonusRuleController::class);

    // =========================
    // Invoices
    // =========================
    Route::prefix('invoices')->group(function (): void {
        Route::post('{id}/issue', [\App\Http\Controllers\Api\InvoiceController::class, 'issue'])->name('invoices.issue');
        Route::post('{id}/mark-paid', [\App\Http\Controllers\Api\InvoiceController::class, 'markPaid'])->name('invoices.mark-paid');
        Route::post('{id}/send-cqt', [\App\Http\Controllers\Api\InvoiceController::class, 'sendCqt'])->name('invoices.send-cqt');
        Route::post('{id}/cancel', [\App\Http\Controllers\Api\InvoiceController::class, 'cancel'])->name('invoices.cancel');
    });
    Route::apiResource('invoices', \App\Http\Controllers\Api\InvoiceController::class);

    // =========================
    // Payroll Adjustments
    // =========================
    Route::prefix('payroll-adjustments')->group(function (): void {
        Route::post('{id}/approve', [\App\Http\Controllers\Api\PayrollAdjustmentController::class, 'approve'])->name('payroll-adjustments.approve');
        Route::post('{id}/reject', [\App\Http\Controllers\Api\PayrollAdjustmentController::class, 'reject'])->name('payroll-adjustments.reject');
        Route::get('/', [\App\Http\Controllers\Api\PayrollAdjustmentController::class, 'index'])->name('payroll-adjustments.index');
        Route::post('/', [\App\Http\Controllers\Api\PayrollAdjustmentController::class, 'store'])->name('payroll-adjustments.store');
        Route::get('{id}', [\App\Http\Controllers\Api\PayrollAdjustmentController::class, 'show'])->name('payroll-adjustments.show');
        Route::put('{id}', [\App\Http\Controllers\Api\PayrollAdjustmentController::class, 'update'])->name('payroll-adjustments.update');
        Route::patch('{id}', [\App\Http\Controllers\Api\PayrollAdjustmentController::class, 'update'])->name('payroll-adjustments.patch');
        Route::delete('{id}', [\App\Http\Controllers\Api\PayrollAdjustmentController::class, 'destroy'])->name('payroll-adjustments.destroy');
    });

    // =========================
    // Payrolls
    // =========================
    Route::prefix('payrolls')->group(function (): void {
        Route::post('{id}/approve', [\App\Http\Controllers\Api\PayrollController::class, 'approve'])->name('payrolls.approve');
        Route::post('{id}/lock', [\App\Http\Controllers\Api\PayrollController::class, 'lock'])->name('payrolls.lock');
        Route::post('{id}/mark-paid', [\App\Http\Controllers\Api\PayrollController::class, 'markPaid'])->name('payrolls.mark-paid');
        Route::get('{id}/export', [\App\Http\Controllers\Api\PayrollController::class, 'export'])->name('payrolls.export');
        Route::get('driver/{driverId}', [\App\Http\Controllers\Api\PayrollController::class, 'driverMonthlySalary'])->name('payrolls.driver-monthly');
    });
    Route::apiResource('payrolls', \App\Http\Controllers\Api\PayrollController::class);

    // =========================
    // Driver Schedules
    // =========================
    Route::prefix('driver-schedules')->group(function (): void {
        Route::post('{driverWorkSchedule}/submit', [\App\Http\Controllers\Api\DriverScheduleController::class, 'submit'])->name('driver-schedules.submit');
        Route::post('{driverWorkSchedule}/approve', [\App\Http\Controllers\Api\DriverScheduleController::class, 'approve'])->name('driver-schedules.approve');
        Route::post('{driverWorkSchedule}/reject', [\App\Http\Controllers\Api\DriverScheduleController::class, 'reject'])->name('driver-schedules.reject');
        Route::post('{driverWorkSchedule}/lock', [\App\Http\Controllers\Api\DriverScheduleController::class, 'lock'])->name('driver-schedules.lock');
        Route::post('{driverWorkSchedule}/override', [\App\Http\Controllers\Api\DriverScheduleController::class, 'override'])->name('driver-schedules.override');
        Route::get('{driverWorkSchedule}/hos-check', [\App\Http\Controllers\Api\DriverScheduleController::class, 'hosCheck'])->name('driver-schedules.hos-check');
        Route::post('{driverWorkSchedule}/hos-check', [\App\Http\Controllers\Api\DriverScheduleController::class, 'hosCheck'])->name('driver-schedules.hos-check.post');
    });
    Route::apiResource('driver-schedules', \App\Http\Controllers\Api\DriverScheduleController::class);

    // =========================
    // Attendance
    // =========================
    Route::prefix('attendance')->group(function (): void {
        Route::post('check-in', [\App\Http\Controllers\Api\AttendanceController::class, 'checkIn'])->name('attendance.check-in');
        Route::post('check-out', [\App\Http\Controllers\Api\AttendanceController::class, 'checkOut'])->name('attendance.check-out');
        Route::patch('{id}/adjust', [\App\Http\Controllers\Api\AttendanceController::class, 'adjust'])->name('attendance.adjust');
        Route::get('/', [\App\Http\Controllers\Api\AttendanceController::class, 'index'])->name('attendance.index');
    });

    // Legacy aliases for FE compatibility
    Route::prefix('attendances')->group(function (): void {
        Route::get('/', [\App\Http\Controllers\Api\AttendanceController::class, 'index'])->name('attendances.index');
        Route::post('check-in', [\App\Http\Controllers\Api\AttendanceController::class, 'checkIn'])->name('attendances.check-in');
        Route::post('check-out', [\App\Http\Controllers\Api\AttendanceController::class, 'checkOut'])->name('attendances.check-out');
        Route::patch('{id}/adjust', [\App\Http\Controllers\Api\AttendanceController::class, 'adjust'])->name('attendances.adjust');
        Route::get('late', [\App\Http\Controllers\Api\AttendanceController::class, 'late'])->name('attendances.late');
        Route::get('late/list', [\App\Http\Controllers\Api\AttendanceController::class, 'late'])->name('attendances.late.list');
        Route::post('late/notify', [\App\Http\Controllers\Api\AttendanceController::class, 'notifyLate'])->name('attendances.late.notify');
    });

    // =========================
    // Leave
    // =========================
    Route::prefix('leave')->group(function (): void {
        Route::get('types', [\App\Http\Controllers\Api\LeaveController::class, 'types'])->name('leave.types');
        Route::post('{leaveRequest}/approve', [\App\Http\Controllers\Api\LeaveController::class, 'approve'])->name('leave.approve');
        Route::post('{leaveRequest}/reject', [\App\Http\Controllers\Api\LeaveController::class, 'reject'])->name('leave.reject');
        Route::post('{leaveRequest}/cancel', [\App\Http\Controllers\Api\LeaveController::class, 'cancel'])->name('leave.cancel');
    });
    Route::apiResource('leave', \App\Http\Controllers\Api\LeaveController::class)->only(['index', 'store', 'show']);

    // =========================
    // Overtime
    // =========================
    Route::prefix('overtime')->group(function (): void {
        Route::post('{overtimeRequest}/approve', [\App\Http\Controllers\Api\OvertimeController::class, 'approve'])->name('overtime.approve');
        Route::post('{overtimeRequest}/reject', [\App\Http\Controllers\Api\OvertimeController::class, 'reject'])->name('overtime.reject');
    });
    Route::apiResource('overtime', \App\Http\Controllers\Api\OvertimeController::class)->only(['index', 'store', 'show']);

    // =========================
    // Violations
    // =========================
    Route::prefix('violations')->group(function (): void {
        Route::post('{violation}/confirm', [\App\Http\Controllers\Api\ViolationController::class, 'confirm'])->name('violations.confirm');
        Route::post('{violation}/dispute', [\App\Http\Controllers\Api\ViolationController::class, 'dispute'])->name('violations.dispute');
        Route::post('{violation}/resolve-dispute', [\App\Http\Controllers\Api\ViolationController::class, 'resolveDispute'])->name('violations.resolve-dispute');
        Route::post('{violation}/waive', [\App\Http\Controllers\Api\ViolationController::class, 'waive'])->name('violations.waive');
    });
    Route::apiResource('violations', \App\Http\Controllers\Api\ViolationController::class)->only(['index', 'store', 'show']);

    // =========================
    // RBAC
    // =========================
    Route::apiResource('users', \App\Http\Controllers\Api\User\UserController::class);
    Route::post('roles/{role}/permissions', [\App\Http\Controllers\Api\RoleController::class, 'syncPermissions'])->name('roles.permissions');
    Route::apiResource('roles', \App\Http\Controllers\Api\RoleController::class);
    Route::get('permissions', [\App\Http\Controllers\Api\PermissionController::class, 'index']);
    Route::get('permissions/{permission}', [\App\Http\Controllers\Api\PermissionController::class, 'show']);

    // =========================
    // Dashboard & Reports
    // =========================
    Route::prefix('reports')->group(function (): void {
        Route::get('dashboard', [\App\Http\Controllers\Api\ReportsController::class, 'dashboard']);
        Route::get('payroll-summary', [\App\Http\Controllers\Api\ReportsController::class, 'payrollSummary']);
        Route::get('revenue-summary', [\App\Http\Controllers\Api\ReportsController::class, 'revenueSummary']);
        // ... continue 
    });

    // =========================
    // AI Assistant
    // =========================
    Route::post('ai/business-assist', [\App\Http\Controllers\Api\AiAdvisorController::class, 'businessAssist']);

    // =========================
    // Legacy Compatibility
    // =========================
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
            'data' => $drivers,
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
        'version' => 'api',
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
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])
        ->middleware('throttle:3,1');
    Route::post('/check-otp', [AuthController::class, 'checkOtp'])
        ->middleware('throttle:10,1');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])
        ->middleware('throttle:5,1');
});



// Protected routes: authenticated users (legacy)
Route::middleware(['auth:sanctum', 'tenant.context', 'track.actions'])->group($registerAuthenticatedRoutes);

// Protected routes: admin only (legacy)
Route::middleware(['auth:sanctum', 'tenant.context', 'track.actions', 'role:admin'])->group($registerAdminRoutes);

