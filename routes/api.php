<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CargoTypeController;
// use App\Http\Controllers\Api\CetaSpecController; // Removed CetaSpecController
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\CostCategoryController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\CustomerGroupController;
use App\Http\Controllers\Api\DriverController;
use App\Http\Controllers\Api\ShippingFeeController;
use App\Http\Controllers\Api\TripController;
use App\Http\Controllers\Api\UploadController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\VehicleController;
use App\Http\Controllers\Api\VehicleAssignmentController;
use App\Http\Controllers\Api\VehicleTypeController; // Added VehicleTypeController
use App\Http\Controllers\Api\ReportsController;
use App\Http\Controllers\Api\PayrollsController;
use App\Http\Controllers\Api\ActivityLogsController;
use App\Http\Controllers\Api\LeaveController;
use App\Http\Controllers\Api\NotificationController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\FallbackController;
use App\Http\Controllers\Api\AutoStubsController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Toàn bộ nghiệp vụ dùng một prefix `/api/...` (không mirror `/api/v1`).
| Convention:
| - Dùng Route::apiResource cho các resource CRUD chuẩn.
| - Các action đặc thù (không thuộc CRUD) đặt trong group của resource đó.
| - Tên resource dùng kebab-case (vd: vehicle-types).
| - Controller nên "mỏng", chỉ điều phối request/response, logic nằm trong Service/Action.
|
*/

// --- Public Routes ---
Route::get('/', function () {
    return response()->json([
        'success' => true,
        'message' => 'API is running',
        'timestamp' => now()->toDateTimeString(),
    ]);
});

// Fallback for any unmatched API route — returns structured 501 Not Implemented
Route::fallback([FallbackController::class, 'handle']);

// --- Auto-generated non-breaking stubs for frontend-used endpoints missing on backend
// These routes are intentionally protected by the standard API middleware so unauthenticated
// requests still receive 401/403 as expected. Each stub returns a 501 Not Implemented JSON.
// Note: Public auth endpoints (login, register, etc.) are registered separately in AuthController section.
Route::middleware(['auth:sanctum', 'tenant.context', 'track.actions'])->group(function (): void {
    $paths = [
        'admin/companies',
        'attendance/check-out',
        'attendances',
        'attendances/late/list',
        'attendances/late/notify',
        'auth/actions',
        'customers/search',
        'documentation',
        'leave/balance',
        'payments',
        'payroll-adjustments',
        'price-lists',
        'public-holidays',
        'salary-adjustments',
        'v2/employees',
        'workforce/absences',
    ];

    foreach ($paths as $p) {
        Route::any($p, [AutoStubsController::class, 'notImplemented']);
    }
});

// --- Public Auth Endpoints (non-breaking stubs for unimplemented auth endpoints) ---
// These endpoints don't require authentication but may accept public data
Route::controller(AutoStubsController::class)->prefix('auth')->group(function (): void {
    Route::post('/check-otp', 'notImplemented');
    Route::post('/forgot-password', 'notImplemented')->middleware('throttle:3,1');
    Route::post('/reset-password', 'notImplemented')->middleware('throttle:5,1');
    Route::post('/social/login', 'notImplemented')->middleware('throttle:10,1');
    Route::post('/me', 'notImplemented');
});

Route::get('/health', fn() => response()->json(['success' => true, 'message' => 'API is running', 'timestamp' => now()->toDateTimeString()]));

Route::controller(AuthController::class)->prefix('auth')->group(function (): void {
    Route::post('/login', 'login')->middleware('throttle:5,1');
    Route::post('/social/login', 'socialLogin')->middleware('throttle:10,1');
    Route::post('/refresh-token', 'refreshByToken')->middleware('throttle:20,1');
    Route::post('/forgot-password', 'forgotPassword')->middleware('throttle:3,1');
    Route::post('/check-otp', 'checkOtp')->middleware('throttle:10,1');
    Route::post('/reset-password', 'resetPassword')->middleware('throttle:5,1');
});

// --- Authenticated Routes ---
Route::middleware(['auth:sanctum', 'tenant.context', 'track.actions'])->group(function (): void {
    // region Auth & User Management
    Route::controller(AuthController::class)->prefix('auth')->group(function (): void {
        Route::post('/logout', 'logout');
        Route::post('/refresh', 'refresh');
        Route::get('/me', 'me');
        Route::patch('/password', 'changePassword');
        Route::get('/logs', 'logs');
        Route::get('/actions', 'actions');
        Route::get('/sessions', 'sessions');
        Route::get('/sessions/summary', 'sessionsSummary');
        Route::patch('/sessions/{sessionId}/revoke', 'revokeSession');
        Route::patch('/sessions/{sessionId}/lock-account', 'lockAccountForSession');
    });

    Route::apiResource('users', UserController::class);
    // These routes still rely on CetaSpecController, commenting out for now
    // Route::put('users/{id}/permissions', [CetaSpecController::class, 'nestedStore'])
    //     ->defaults('parent', 'users')->defaults('child', 'user-permissions');
    // Route::patch('users/{id}/status', [CetaSpecController::class, 'action])
    //     ->defaults('resource', 'users')->defaults('actionName', 'status');
    // Route::post('users/{id}/reset-password', [CetaSpecController::class, 'action'])
    //     ->defaults('resource', 'users')->defaults('actionName', 'reset-password');

    Route::apiResource('companies', CompanyController::class);
    Route::patch('companies/{company}/status', [CompanyController::class, 'updateStatus']);
    Route::patch('users/{user}/status', [UserController::class, 'updateStatus']);
    Route::post('users/{user}/reset-password', [UserController::class, 'resetPassword']);
    Route::match(['get', 'put'], 'users/{user}/permissions', [UserController::class, 'permissions']);
    // Route::patch('companies/{id}/status', [CetaSpecController::class, 'action']) // Commented out as CetaSpecController is removed
    //     ->defaults('resource', 'companies')->defaults('actionName', 'status');
    // endregion

    // region Activity Logs
    Route::controller(ActivityLogsController::class)->prefix('activity-logs')->group(function (): void {
        Route::get('/', 'index');
    });
    // endregion

    // region Upload & Chat
    Route::controller(UploadController::class)->prefix('upload')->group(function (): void {
        Route::post('/', 'store');
        Route::post('/image', 'storeImage');
        Route::post('/document', 'storeImage'); // Changed to storeImage for consistency
        // TODO: DELETE /upload thiếu ID, cần sửa thành DELETE /uploads/{id} và trỏ tới controller/method hợp lệ.
        // Route::delete('/', [CetaSpecController::class, 'uploadDelete']);
    });

    Route::controller(ChatController::class)->prefix('chat')->group(function (): void {
        Route::get('/sessions', 'sessions');
        Route::delete('/sessions/{sessionId}', 'destroySession');
        Route::get('/messages', 'index');
        Route::post('/messages', 'store');
        Route::post('/messages/stream', 'stream');
    });
    // endregion

    // region Catalogs (Danh mục)
    Route::apiResource('vehicle-types', VehicleTypeController::class)->except('show');
    Route::patch('vehicle-types/reorder', [VehicleTypeController::class, 'reorder']); // Using new VehicleTypeController
    // Route::patch('vehicle-types/reorder', [CetaSpecController::class, 'action']) // Commented out as CetaSpecController is removed
    //     ->defaults('resource', 'vehicle-types')->defaults('actionName', 'reorder');

    Route::apiResource('cargo-types', CargoTypeController::class)->except('show');
    Route::apiResource('cost-categories', CostCategoryController::class)->except('show');
    // Route::group(['defaults' => ['resource' => 'spare-parts']], function () { // Commented out as CetaSpecController is removed
    //     Route::apiResource('spare-parts', CetaSpecController::class)->except('show');
    // });
    // Route::group(['defaults' => ['resource' => 'locations']], function () { // Commented out as CetaSpecController is removed
    //     Route::apiResource('locations', CetaSpecController::class);
    // });
    // Route::get('locations/search', [CetaSpecController::class, 'index'])->defaults('resource', 'locations'); // Commented out as CetaSpecController is removed
    // Route::group(['defaults' => ['resource' => 'route-templates']], function () { // Commented out as CetaSpecController is removed
    //     Route::apiResource('route-templates', CetaSpecController::class);
    // });

    // Route::get('order-status-configs', [CetaSpecController::class, 'index'])->defaults('resource', 'order-status-configs'); // Commented out as CetaSpecController is removed
    // // NOTE: Dùng POST để tạo mới/cập nhật cả bộ config, thay vì PUT trên collection.
    // Route::post('order-status-configs', [CetaSpecController::class, 'store'])->defaults('resource', 'order-status-configs'); // Commented out as CetaSpecController is removed
    // endregion

    // region Customers & Pricing
    Route::apiResource('customers', CustomerController::class);
    Route::get('debt-overview', [\App\Http\Controllers\Api\DebtOverviewController::class, 'index']);

    Route::apiResource('customer-groups', CustomerGroupController::class);

    // Route::prefix('price-lists')->controller(CetaSpecController::class)->group(function () { // Commented out as CetaSpecController is removed
    //     Route::put('/{id}', 'update')->defaults('resource', 'price-lists');
    //     Route::delete('/{id}', 'destroy')->defaults('resource', 'price-lists');
    //     Route::get('/{id}/items', 'nestedIndex')->defaults('parent', 'price-lists')->defaults('child', 'price-list-items');
    //     Route::post('/{id}/items', 'nestedStore')->defaults('parent', 'price-lists')->defaults('child', 'price-list-items');
    //     Route::delete('/{id}/items/{itemId}', 'nestedDestroy')->defaults('parent', 'price-lists')->defaults('child', 'price-list-items');
    // });
    // Route::post('customers/{id}/price-lists', [CetaSpecController::class, 'nestedStore']) // Commented out as CetaSpecController is removed
    //     ->defaults('parent', 'customers')->defaults('child', 'price-lists');
    // Route::get('customers/{id}/price-lists', [CetaSpecController::class, 'nestedIndex']) // Commented out as CetaSpecController is removed
    //     ->defaults('parent', 'customers')->defaults('child', 'price-lists');

    // Route::post('prices/lookup', [CetaSpecController::class, 'priceLookup']); // Commented out as CetaSpecController is removed
    Route::post('shipping-fees/calculate', [ShippingFeeController::class, 'lookup']);
    // endregion

    // region Fleet (Đội xe)
    Route::apiResource('vehicles', VehicleController::class);
    Route::get('vehicle-expenses', fn() => response()->json(['success' => true, 'data' => [], 'message' => 'Migrated to trip-costs']));
    Route::get('vehicles/available', [VehicleController::class, 'available']);
    Route::patch('vehicles/{vehicle}/status', [VehicleController::class, 'updateStatus']);
    // NOTE: Các route RPC như 'expiring-documents' nên được thay bằng filter trên resource chính.
    // Ví dụ: GET /vehicle-documents?status=expiring
    // Route::get('vehicles/expiring-documents', [CetaSpecController::class, 'index'])->defaults('resource', 'vehicle-documents'); // Commented out as CetaSpecController is removed
    // Route::get('vehicles/maintenance-due', [CetaSpecController::class, 'index'])->defaults('resource', 'maintenance-schedules'); // Commented out as CetaSpecController is removed

    // Route::group(['defaults' => ['parent' => 'vehicles', 'child' => 'vehicle-documents']], function () { // Commented out as CetaSpecController is removed
    //     Route::apiResource('vehicles.documents', CetaSpecController::class)->shallow();
    // });

    Route::apiResource('vehicle-assignments', VehicleAssignmentController::class);
    Route::patch('vehicle-assignments/{vehicle_assignment}/release', [VehicleAssignmentController::class, 'release']);

    // Route::group(['defaults' => ['parent' => 'vehicles', 'child' => 'maintenance-schedules']], function () { // Commented out as CetaSpecController is removed
    //     Route::apiResource('vehicles.maintenance-schedules', CetaSpecController::class)->shallow();
    // });
    // Route::group(['defaults' => ['parent' => 'vehicles', 'child' => 'maintenance-records']], function () { // Commented out as CetaSpecController is removed
    //     Route::apiResource('vehicles.maintenance-records', CetaSpecController::class)->shallow();
    // });
    // Route::patch('maintenance-records/{id}/complete', [CetaSpecController::class, 'action']) // Commented out as CetaSpecController is removed
    //     ->defaults('resource', 'maintenance-records')->defaults('actionName', 'complete');
    // endregion

    // region Drivers (Tài xế)
    Route::apiResource('drivers', DriverController::class);
    // Route::get('drivers/available', [CetaSpecController::class, 'available'])->defaults('resource', 'drivers'); // Commented out as CetaSpecController is removed
    // Route::patch('drivers/{id}/status', [CetaSpecController::class, 'action']) // Commented out as CetaSpecController is removed
    //     ->defaults('resource', 'drivers')->defaults('actionName', 'status');
    // Route::get('drivers/expiring-documents', [CetaSpecController::class, 'index'])->defaults('resource', 'driver-documents'); // Commented out as CetaSpecController is removed
    // Route::group(['defaults' => ['parent' => 'drivers', 'child' => 'driver-documents']], function () { // Commented out as CetaSpecController is removed
    //     Route::apiResource('drivers.documents', CetaSpecController::class)->shallow();
    // });
    // Route::group(['defaults' => ['resource' => 'driver-teams']], function () { // Commented out as CetaSpecController is removed
    //     Route::apiResource('driver-teams', CetaSpecController::class)->except('show');
    // });
    // endregion

    // region Schedules & Leave (Lịch làm việc & Nghỉ phép)
    // NOTE: Đổi tên 'work-schedules' thành 'driver-work-schedules' cho nhất quán.
    // Route::group(['defaults' => ['resource' => 'driver-work-schedules']], function () { // Commented out as CetaSpecController is removed
    //     Route::apiResource('driver-work-schedules', CetaSpecController::class)->except(['show', 'update']);
    // });
    // Route::post('driver-work-schedules/generate', [CetaSpecController::class, 'store'])->defaults('resource', 'driver-work-schedules'); // Commented out as CetaSpecController is removed
    // Route::patch('driver-work-schedules/{id}/submit', [CetaSpecController::class, 'action']) // Commented out as CetaSpecController is removed
    //     ->defaults('resource', 'driver-work-schedules')->defaults('actionName', 'submit');
    // Route::patch('driver-work-schedules/{id}/approve', [CetaSpecController::class, 'action']) // Commented out as CetaSpecController is removed
    //     ->defaults('resource', 'driver-work-schedules')->defaults('actionName', 'approve');
    // Route::patch('driver-work-schedules/{id}/reject', [CetaSpecController::class, 'action']) // Commented out as CetaSpecController is removed
    //     ->defaults('resource', 'driver-work-schedules')->defaults('actionName', 'reject');

    // Route::group(['defaults' => ['resource' => 'leave-requests']], function () { // Commented out as CetaSpecController is removed
    //     Route::apiResource('leave-requests', CetaSpecController::class);
    // });
    // Route::patch('leave-requests/{id}/approve', [CetaSpecController::class, 'action']) // Commented out as CetaSpecController is removed
    //     ->defaults('resource', 'leave-requests')->defaults('actionName', 'approve');
    // Route::patch('leave-requests/{id}/reject', [CetaSpecController::class, 'action']) // Commented out as CetaSpecController is removed
    //     ->defaults('resource', 'leave-requests')->defaults('actionName', 'reject');
    // Route::patch('leave-requests/{id}/cancel', [CetaSpecController::class, 'action']) // Commented out as CetaSpecController is removed
    //     ->defaults('resource', 'leave-requests')->defaults('actionName', 'cancel');

    // Route::group(['defaults' => ['resource' => 'leave-types']], function () { // Commented out as CetaSpecController is removed
    //     Route::apiResource('leave-types', CetaSpecController::class)->except('show');
    // });
    // Route::patch('leave-types/{id}/status', [CetaSpecController::class, 'action']) // Commented out as CetaSpecController is removed
    //     ->defaults('resource', 'leave-types')->defaults('actionName', 'status');

    // Route::group(['defaults' => ['resource' => 'overtime']], function () { // Commented out as CetaSpecController is removed
    //     Route::apiResource('overtime', CetaSpecController::class);
    // });
    // Route::patch('overtime/{id}/approve', [CetaSpecController::class, 'action']) // Commented out as CetaSpecController is removed
    //     ->defaults('resource', 'overtime')->defaults('actionName', 'approve');
    // Route::patch('overtime/{id}/reject', [CetaSpecController::class, 'action']) // Commented out as CetaSpecController is removed
    //     ->defaults('resource', 'overtime')->defaults('actionName', 'reject');
    // endregion

    // region Trips & Costs (Chuyến xe & Chi phí)
    Route::apiResource('trips', TripController::class);
    // Route::group(['defaults' => ['resource' => 'transport-requests']], function () { // Commented out as CetaSpecController is removed
    //     Route::apiResource('transport-requests', CetaSpecController::class);
    // });
    // Route::prefix('trips/{id}')->controller(CetaSpecController::class)->group(function () { // Commented out as CetaSpecController is removed
    //     foreach (['assign', 'start', 'deliver', 'complete', 'cancel', 'change-vehicle', 'change-driver'] as $tripAction) {
    //         Route::patch("/{$tripAction}", 'action')->defaults('resource', 'trips')->defaults('actionName', $tripAction);
    //     }
    // });

    // Route::group(['defaults' => ['parent' => 'trips', 'child' => 'trip-stops']], function () { // Commented out as CetaSpecController is removed
    //     Route::apiResource('trips.stops', CetaSpecController::class)->shallow();
    // });
    // Route::patch('stops/{childId}/arrive', [CetaSpecController::class, 'action']) // Commented out as CetaSpecController is removed
    //     ->defaults('resource', 'trip-stops')->defaults('actionName', 'arrive');
    // Route::patch('stops/{childId}/complete', [CetaSpecController::class, 'action']) // Commented out as CetaSpecController is removed
    //     ->defaults('resource', 'trip-stops')->defaults('actionName', 'complete');

    // Route::group(['defaults' => ['parent' => 'trips', 'child' => 'trip-surcharges']], function () { // Commented out as CetaSpecController is removed
    //     Route::apiResource('trips.surcharges', CetaSpecController::class)->shallow();
    // });
    // Route::group(['defaults' => ['parent' => 'trips', 'child' => 'trip-documents']], function () { // Commented out as CetaSpecController is removed
    //     Route::apiResource('trips.documents', CetaSpecController::class)->shallow();
    // });
    // Route::group(['defaults' => ['parent' => 'trips', 'child' => 'trip-costs']], function () { // Commented out as CetaSpecController is removed
    //     Route::apiResource('trips.costs', CetaSpecController::class)->shallow();
    // });
    // Route::group(['defaults' => ['resource' => 'trip-costs']], function () { // Commented out as CetaSpecController is removed
    //     Route::apiResource('trip-costs', CetaSpecController::class);
    // });

    // Route::group(['defaults' => ['resource' => 'cost-approvals']], function () { // Commented out as CetaSpecController is removed
    //     Route::apiResource('cost-approvals', CetaSpecController::class)->only(['index', 'show']);
    // });
    // Route::patch('cost-approvals/{id}/approve', [CetaSpecController::class, 'action']) // Commented out as CetaSpecController is removed
    //     ->defaults('resource', 'cost-approvals')->defaults('actionName', 'approve');
    // Route::patch('cost-approvals/{id}/reject', [CetaSpecController::class, 'action']) // Commented out as CetaSpecController is removed
    //     ->defaults('resource', 'cost-approvals')->defaults('actionName', 'reject');
    // endregion

    // region Accounting (Kế toán)
    // Route::group(['defaults' => ['resource' => 'reconciliations']], function () { // Commented out as CetaSpecController is removed
    //     Route::apiResource('reconciliations', CetaSpecController::class);
    // });
    // Route::put('reconciliations/{id}/items/{itemId}', [CetaSpecController::class, 'nestedUpdate']) // Commented out as CetaSpecController is removed
    //     ->defaults('parent', 'reconciliations')->defaults('child', 'reconciliation-items');
    // Route::patch('reconciliations/{id}/confirm', [CetaSpecController::class, 'action']) // Commented out as CetaSpecController is removed
    //     ->defaults('resource', 'reconciliations')->defaults('actionName', 'confirm');
    // Route::get('reconciliations/{id}/export', [CetaSpecController::class, 'show'])->defaults('resource', 'reconciliations'); // Commented out as CetaSpecController is removed

    // Route::group(['defaults' => ['parent' => 'customers', 'child' => 'payments']], function () { // Commented out as CetaSpecController is removed
    //     Route::apiResource('customers.payments', CetaSpecController::class)->shallow();
    // });

    Route::apiResource('invoices', \App\Http\Controllers\Api\InvoiceController::class);
    // Route::prefix('invoices/{id}')->controller(CetaSpecController::class)->group(function () { // Commented out as CetaSpecController is removed
    //     Route::patch('/issue', 'action')->defaults('resource', 'invoices')->defaults('actionName', 'issue');
    //     Route::patch('/mark-paid', 'action')->defaults('resource', 'invoices')->defaults('actionName', 'mark-paid');
    //     Route::patch('/cancel', 'action')->defaults('resource', 'invoices')->defaults('actionName', 'cancel');
    //     Route::patch('/email', 'action')->defaults('resource', 'invoices')->defaults('actionName', 'email');
    //     Route::get('/status-histories', 'nestedIndex')->defaults('parent', 'invoices')->defaults('child', 'invoice-status-histories');
    //     Route::get('/cqt', 'show')->defaults('resource', 'invoices');
    //     Route::get('/pdf', 'show')->defaults('resource', 'invoices');
    // });
    // endregion

    // region Notifications
    Route::prefix('notifications')->controller(NotificationController::class)->group(function () {
        Route::get('/', 'index');
        Route::get('/unread-count', 'unreadCount');
        Route::patch('/read-all', 'markAllRead');
        Route::patch('/{id}/read', 'markRead');
        Route::delete('/{id}', 'destroy');
    });

    // Route::prefix('notifications')->controller(CetaSpecController::class)->group(function () { // Commented out as CetaSpecController is removed
    //     Route::get('/', 'index')->defaults('resource', 'notifications');
    //     Route::get('/unread-count', 'report')->defaults('reportType', 'notifications-unread');
    //     Route::patch('/read-all', 'action')->defaults('resource', 'notifications')->defaults('id', 'all')->defaults('actionName', 'read');
    //     Route::patch('/{id}/read', 'action')->defaults('resource', 'notifications')->defaults('actionName', 'read');
    //     Route::delete('/{id}', 'destroy')->defaults('resource', 'notifications');
    // });
    // endregion

    // region Reports & Dispatch
    Route::prefix('reports')->controller(ReportsController::class)->group(function () {
        foreach (['dashboard', 'revenue', 'costs', 'profit', 'trips', 'vehicles', 'drivers', 'debt', 'maintenance'] as $reportType) {
            Route::get("/{$reportType}", 'report')->defaults('reportType', $reportType);
        }
        Route::post('/export', 'report')->defaults('reportType', 'export');
        Route::get('/payroll/export', 'report')->defaults('reportType', 'payroll-export');
    });

    Route::prefix('dispatch')->controller(\App\Http\Controllers\Api\DispatchBoardController::class)->group(function () {
        Route::get('/board', 'board');
        Route::get('/unassigned-trips', 'unassignedTrips');
        Route::get('/daily-summary', 'dailySummary');
    });

    Route::prefix('payrolls')->controller(PayrollsController::class)->group(function () {
        Route::get('/', 'index');
        Route::post('/generate', 'generate');
        Route::get('/export', 'export');
        Route::get('/my-salary', 'mySalary');
        Route::post('/{id}/approve', 'approve');
        Route::post('/{id}/lock', 'lock');
    });

    Route::apiResource('payroll-lines', \App\Http\Controllers\Api\PayrollLineController::class)->only(['index', 'show']);
    Route::apiResource('payroll-adjustments', \App\Http\Controllers\Api\PayrollAdjustmentController::class);
    Route::get('allowances', [\App\Http\Controllers\Api\PayrollAdjustmentController::class, 'allowances'])->name('allowances.index');
    Route::get('deductions', [\App\Http\Controllers\Api\PayrollAdjustmentController::class, 'deductions'])->name('deductions.index');
    // endregion

    // region Legacy Workforce (tạm giữ, cần refactor)
    // Route::group(['defaults' => ['resource' => 'offices']], function () { // Commented out as CetaSpecController is removed
    //     Route::apiResource('offices', CetaSpecController::class);
    // });
    // Route::group(['defaults' => ['resource' => 'departments']], function () { // Commented out as CetaSpecController is removed
    //     Route::apiResource('departments', CetaSpecController::class)->except('show');
    // });
    // Route::group(['defaults' => ['resource' => 'positions']], function () { // Commented out as CetaSpecController is removed
    //     Route::apiResource('positions', CetaSpecController::class)->except('show');
    // });
    // Route::group(['defaults' => ['resource' => 'employees']], function () { // Commented out as CetaSpecController is removed
    //     Route::apiResource('employees', CetaSpecController::class);
    // });
    // endregion
});
