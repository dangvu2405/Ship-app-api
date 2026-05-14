<?php

use App\Http\Controllers\Api\ActivityLogsController;
use App\Http\Controllers\Api\AuthController;
// use App\Http\Controllers\Api\CetaSpecController; // Removed CetaSpecController
use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\AutoStubsController;
use App\Http\Controllers\Api\CargoTypeController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\CostApprovalController;
use App\Http\Controllers\Api\CostCategoryController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\CustomerGroupController;
use App\Http\Controllers\Api\DebtOverviewController;
use App\Http\Controllers\Api\DispatchBoardController;
use App\Http\Controllers\Api\DriverController;
use App\Http\Controllers\Api\DriverWorkScheduleController;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\FallbackController;
use App\Http\Controllers\Api\FleetDocumentController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\LeaveController;
use App\Http\Controllers\Api\MaintenanceController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PayrollAdjustmentController;
use App\Http\Controllers\Api\PayrollLineController;
use App\Http\Controllers\Api\PayrollsController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PriceListController;
use App\Http\Controllers\Api\ReconciliationController;
use App\Http\Controllers\Api\ReportsController;
use App\Http\Controllers\Api\ShippingFeeController;
use App\Http\Controllers\Api\TripController;
use App\Http\Controllers\Api\TripCostController;
use App\Http\Controllers\Api\UploadController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\VehicleAssignmentController;
use App\Http\Controllers\Api\VehicleController;
use App\Http\Controllers\Api\VehicleTypeController;
use Illuminate\Support\Facades\Route;

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
        'salary-adjustments',
    ];

    foreach ($paths as $p) {
        Route::any($p, [AutoStubsController::class, 'notImplemented']);
    }
});

Route::get('/health', fn () => response()->json(['success' => true, 'message' => 'API is running', 'timestamp' => now()->toDateTimeString()]));

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
    Route::get('admin/companies', [CompanyController::class, 'index']);
    Route::post('admin/companies', [CompanyController::class, 'store']);
    Route::get('admin/companies/{company}', [CompanyController::class, 'show']);
    Route::put('admin/companies/{company}', [CompanyController::class, 'update']);
    Route::patch('admin/companies/{company}', [CompanyController::class, 'update']);
    Route::delete('admin/companies/{company}', [CompanyController::class, 'destroy']);
    Route::patch('admin/companies/{company}/status', [CompanyController::class, 'updateStatus']);
    Route::apiResource('admin-companies', CompanyController::class)
        ->parameters(['admin-companies' => 'company']);
    Route::patch('admin-companies/{company}/status', [CompanyController::class, 'updateStatus']);

    Route::get('customers/search', [CustomerController::class, 'search']);
    Route::apiResource('customers', CustomerController::class);
    Route::prefix('customers/{customer}')->controller(CustomerController::class)->group(function () {
        Route::get('/trips', 'trips');
        Route::get('/debt', 'debt');
        Route::get('/payments', 'payments');
        Route::post('/payments', 'storePayment');
    });
    Route::apiResource('payments', PaymentController::class);
    Route::get('attendance', [AttendanceController::class, 'index']);
    Route::post('attendance/check-in', [AttendanceController::class, 'checkIn']);
    Route::post('attendance/check-out', [AttendanceController::class, 'checkOut']);
    Route::patch('attendance/{id}/adjust', [AttendanceController::class, 'adjust']);
    Route::get('attendances', [AttendanceController::class, 'index']);
    Route::get('attendances/late/list', [AttendanceController::class, 'lateList']);
    Route::post('attendances/late/notify', [AttendanceController::class, 'lateNotify']);
    Route::get('debt-overview', [DebtOverviewController::class, 'index']);

    Route::apiResource('customer-groups', CustomerGroupController::class);

    Route::get('customers/{customer}/price-lists', [PriceListController::class, 'customerIndex']);
    Route::post('customers/{customer}/price-lists', [PriceListController::class, 'customerStore']);
    Route::get('price-lists/{priceList}/items', [PriceListController::class, 'items']);
    Route::post('price-lists/{priceList}/items', [PriceListController::class, 'storeItem']);
    Route::delete('price-lists/{priceList}/items/{item}', [PriceListController::class, 'destroyItem']);
    Route::apiResource('price-lists', PriceListController::class)
        ->parameters(['price-lists' => 'priceList']);

    // Route::post('prices/lookup', [CetaSpecController::class, 'priceLookup']); // Commented out as CetaSpecController is removed
    Route::post('shipping-fees/calculate', [ShippingFeeController::class, 'lookup']);
    // endregion

    // region Fleet (Đội xe)
    Route::get('vehicle-expenses', fn () => response()->json(['success' => true, 'data' => [], 'message' => 'Migrated to trip-costs']));
    Route::get('vehicles/available', [VehicleController::class, 'available']);
    Route::get('vehicles/expiring-documents', [FleetDocumentController::class, 'expiringVehicleDocuments']);
    Route::apiResource('vehicles', VehicleController::class);
    Route::patch('vehicles/{vehicle}/status', [VehicleController::class, 'updateStatus']);
    // Route::get('vehicles/maintenance-due', [CetaSpecController::class, 'index'])->defaults('resource', 'maintenance-schedules'); // Commented out as CetaSpecController is removed

    Route::get('vehicle-documents', [FleetDocumentController::class, 'vehicleDocuments']);
    Route::post('vehicle-documents', [FleetDocumentController::class, 'storeVehicleDocument']);

    Route::apiResource('vehicle-assignments', VehicleAssignmentController::class);
    Route::patch('vehicle-assignments/{vehicleAssignment}/release', [VehicleAssignmentController::class, 'release']);

    Route::get('maintenance-schedules', [MaintenanceController::class, 'schedules']);
    Route::get('maintenance-records', [MaintenanceController::class, 'records']);
    Route::post('maintenance-records', [MaintenanceController::class, 'storeRecord']);
    Route::patch('maintenance-records/{maintenanceRecord}/complete', [MaintenanceController::class, 'completeRecord']);
    // endregion

    // region Drivers (Tài xế)
    Route::get('drivers/available', [DriverController::class, 'available']);
    Route::get('drivers/expiring-documents', [FleetDocumentController::class, 'expiringDriverDocuments']);
    Route::apiResource('drivers', DriverController::class);
    Route::get('employees', [EmployeeController::class, 'index']);
    Route::get('v2/employees', [EmployeeController::class, 'index']);
    // Route::patch('drivers/{id}/status', [CetaSpecController::class, 'action']) // Commented out as CetaSpecController is removed
    //     ->defaults('resource', 'drivers')->defaults('actionName', 'status');
    // Route::group(['defaults' => ['parent' => 'drivers', 'child' => 'driver-documents']], function () { // Commented out as CetaSpecController is removed
    //     Route::apiResource('drivers.documents', CetaSpecController::class)->shallow();
    // });
    // Route::group(['defaults' => ['resource' => 'driver-teams']], function () { // Commented out as CetaSpecController is removed
    //     Route::apiResource('driver-teams', CetaSpecController::class)->except('show');
    // });
    // endregion

    // region Schedules & Leave (Lịch làm việc & Nghỉ phép)
    Route::post('driver-work-schedules/generate', [DriverWorkScheduleController::class, 'generate']);
    Route::patch('driver-work-schedules/{driverWorkSchedule}/submit', [DriverWorkScheduleController::class, 'submit']);
    Route::patch('driver-work-schedules/{driverWorkSchedule}/approve', [DriverWorkScheduleController::class, 'approve']);
    Route::patch('driver-work-schedules/{driverWorkSchedule}/reject', [DriverWorkScheduleController::class, 'reject']);
    Route::patch('driver-work-schedules/{driverWorkSchedule}/lock', [DriverWorkScheduleController::class, 'lock']);
    Route::patch('driver-work-schedules/{driverWorkSchedule}/override', [DriverWorkScheduleController::class, 'override']);
    Route::get('driver-work-schedules/{driverWorkSchedule}/hos-check', [DriverWorkScheduleController::class, 'hosCheck']);
    Route::apiResource('driver-work-schedules', DriverWorkScheduleController::class)
        ->parameters(['driver-work-schedules' => 'driverWorkSchedule']);
    Route::get('workforce/absences', [DriverWorkScheduleController::class, 'absences']);
    Route::get('public-holidays', [DriverWorkScheduleController::class, 'publicHolidays']);

    Route::get('leave/types', [LeaveController::class, 'types']);
    Route::get('leave/balance', [LeaveController::class, 'balance']);
    Route::get('leave-types', [LeaveController::class, 'types']);
    Route::apiResource('leave-requests', LeaveController::class)
        ->only(['index', 'store', 'show'])
        ->parameters(['leave-requests' => 'leaveRequest']);
    Route::patch('leave-requests/{leaveRequest}/approve', [LeaveController::class, 'approve']);
    Route::patch('leave-requests/{leaveRequest}/reject', [LeaveController::class, 'reject']);
    Route::patch('leave-requests/{leaveRequest}/cancel', [LeaveController::class, 'cancel']);

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
    Route::prefix('trips/{id}')->controller(TripController::class)->group(function (): void {
        Route::patch('/assign', 'assign');
        Route::patch('/start', 'start');
        Route::patch('/deliver', 'deliver');
        Route::patch('/complete', 'complete');
        Route::patch('/cancel', 'cancel');
        Route::patch('/change-vehicle', 'changeVehicle');
        Route::patch('/change-driver', 'changeDriver');
    });

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
    Route::get('trip-costs', [TripCostController::class, 'index']);
    Route::post('trip-costs', [TripCostController::class, 'store']);
    Route::get('trips/{id}/costs', [TripCostController::class, 'index']);
    Route::post('trips/{id}/costs', [TripCostController::class, 'store']);

    Route::get('cost-approvals', [CostApprovalController::class, 'index']);
    Route::patch('cost-approvals/{costApproval}/approve', [CostApprovalController::class, 'approve']);
    Route::patch('cost-approvals/{costApproval}/reject', [CostApprovalController::class, 'reject']);
    // endregion

    // region Accounting (Kế toán)
    Route::apiResource('reconciliations', ReconciliationController::class);
    Route::prefix('reconciliations/{reconciliation}')->controller(ReconciliationController::class)->group(function () {
        Route::get('/items', 'items');
        Route::put('/items/{item}', 'updateItem');
        Route::patch('/confirm', 'confirm');
        Route::patch('/lock', 'lock');
    });

    // Route::group(['defaults' => ['parent' => 'customers', 'child' => 'payments']], function () { // Commented out as CetaSpecController is removed
    //     Route::apiResource('customers.payments', CetaSpecController::class)->shallow();
    // });

    Route::apiResource('invoices', InvoiceController::class);
    Route::prefix('invoices/{invoice}')->controller(InvoiceController::class)->group(function (): void {
        Route::patch('/issue', 'issue');
        Route::patch('/mark-paid', 'markPaid');
        Route::patch('/cancel', 'cancel');
        Route::patch('/email', 'email');
        Route::get('/cqt', 'cqt');
        Route::get('/pdf', 'pdf');
        Route::get('/status-histories', 'statusHistories');
    });
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

    Route::prefix('dispatch')->controller(DispatchBoardController::class)->group(function () {
        Route::get('/board', 'board');
        Route::get('/unassigned-trips', 'unassignedTrips');
        Route::get('/daily-summary', 'dailySummary');
    });

    Route::prefix('payrolls')->controller(PayrollsController::class)->group(function () {
        Route::get('/', 'index');
        Route::post('/generate', 'generate');
        Route::get('/export', 'export');
        Route::get('/my-salary', 'mySalary');
        Route::get('/driver/{id}', 'driverHistory');
        Route::patch('/{id}/approve', 'approve');
        Route::patch('/{id}/lock', 'lock');
        Route::patch('/{id}/mark-paid', 'markPaid');
        Route::get('/{id}/export', 'exportById');
        Route::get('/{id}/export-bhxh', 'exportBhxh');
        Route::get('/{id}/export-pit', 'exportPit');
        Route::get('/{id}/export-payslips', 'exportPayslips');
    });

    Route::apiResource('payroll-lines', PayrollLineController::class)->only(['index', 'show']);
    Route::apiResource('payroll-driver-lines', PayrollLineController::class)->only(['index', 'show']);
    Route::apiResource('payroll-adjustments', PayrollAdjustmentController::class);
    Route::patch('payroll-adjustments/{id}/approve', [PayrollAdjustmentController::class, 'approve']);
    Route::patch('payroll-adjustments/{id}/reject', [PayrollAdjustmentController::class, 'reject']);
    Route::get('allowances', [PayrollAdjustmentController::class, 'allowances'])->name('allowances.index');
    Route::get('deductions', [PayrollAdjustmentController::class, 'deductions'])->name('deductions.index');
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
