<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\DebtOverviewController;
use App\Http\Controllers\Api\DepartmentController;
use App\Http\Controllers\Api\DispatchBoardController;
use App\Http\Controllers\Api\DriverController;
use App\Http\Controllers\Api\DriverScheduleController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\LeaveController;
use App\Http\Controllers\Api\OfficeApplyScheduleController;
use App\Http\Controllers\Api\OfficeController;
use App\Http\Controllers\Api\OvertimeController;
use App\Http\Controllers\Api\PositionController;
use App\Http\Controllers\Api\ReportsController;
use App\Http\Controllers\Api\TripBonusRuleController;
use App\Http\Controllers\Api\TripController;
use App\Http\Controllers\Api\VehicleAssignmentController;
use App\Http\Controllers\Api\VehicleController;
use App\Http\Controllers\Api\VehicleExpenseController;
use App\Http\Controllers\Api\ViolationController;
use App\Http\Controllers\Api\WorkScheduleTemplateController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/**
 * CETA operational surface (Dispatcher+ via office_admin hierarchy).
 */
return static function (): void {
    $has = static function (string $table): bool {
        try {
            return Schema::hasTable($table);
        } catch (\Throwable) {
            return false;
        }
    };

    Route::apiResource('work-schedule-templates', WorkScheduleTemplateController::class)
        ->except(['create', 'edit']);
    if ($has('offices')) {
        Route::post('offices/{office}/apply-schedule', [OfficeApplyScheduleController::class, 'store']);
        Route::apiResource('offices', OfficeController::class);
    }
    if ($has('departments')) {
        Route::apiResource('departments', DepartmentController::class);
    }
    if ($has('positions')) {
        Route::apiResource('positions', PositionController::class);
    }

    Route::get('drivers/available', [DriverController::class, 'available']);
    Route::apiResource('drivers', DriverController::class);

    Route::get('vehicles/available', [VehicleController::class, 'available']);
    Route::apiResource('vehicles', VehicleController::class);
    Route::patch('vehicles/{vehicle}/assignments/release', [VehicleController::class, 'releaseAssignments']);

    Route::get('customers/search', [CustomerController::class, 'search']);
    Route::get('customers/{customer}/trips', [CustomerController::class, 'trips']);
    Route::get('customers/{customer}/debt', [CustomerController::class, 'debt']);
    Route::apiResource('customers', CustomerController::class);

    Route::apiResource('vehicle_assignments', VehicleAssignmentController::class);
    Route::apiResource('vehicle_expenses', VehicleExpenseController::class);

    Route::prefix('trips')->group(function (): void {
        Route::post('{id}/assign', [TripController::class, 'assign'])->name('trips.assign');
        Route::patch('{id}/assign', [TripController::class, 'assign'])->name('trips.assign.patch');
        Route::post('{id}/start', [TripController::class, 'start'])->name('trips.start');
        Route::patch('{id}/start', [TripController::class, 'start'])->name('trips.start.patch');
        Route::post('{id}/pickup', [TripController::class, 'pickup'])->name('trips.pickup');
        Route::patch('{id}/pickup', [TripController::class, 'pickup'])->name('trips.pickup.patch');
        Route::post('{id}/transit', [TripController::class, 'transit'])->name('trips.transit');
        Route::patch('{id}/transit', [TripController::class, 'transit'])->name('trips.transit.patch');
        Route::post('{id}/arrive', [TripController::class, 'arrive'])->name('trips.arrive');
        Route::patch('{id}/arrive', [TripController::class, 'arrive'])->name('trips.arrive.patch');
        Route::patch('{id}/deliver', [TripController::class, 'deliver'])->name('trips.deliver');
        Route::post('{id}/complete', [TripController::class, 'complete'])->name('trips.complete');
        Route::patch('{id}/complete', [TripController::class, 'complete'])->name('trips.complete.patch');
        Route::post('{id}/cancel', [TripController::class, 'cancel'])->name('trips.cancel');
        Route::patch('{id}/cancel', [TripController::class, 'cancel'])->name('trips.cancel.patch');
        Route::post('{id}/delay', [TripController::class, 'delay'])->name('trips.delay');
        Route::patch('{id}/delay', [TripController::class, 'delay'])->name('trips.delay.patch');
        Route::post('{id}/resume', [TripController::class, 'resume'])->name('trips.resume');
        Route::patch('{id}/resume', [TripController::class, 'resume'])->name('trips.resume.patch');
        Route::patch('{id}/change-vehicle', [TripController::class, 'changeVehicle'])->name('trips.change-vehicle');
        Route::patch('{id}/change-driver', [TripController::class, 'changeDriver'])->name('trips.change-driver');
        Route::patch('{id}/details', [TripController::class, 'updateDetails'])->name('trips.details.update');
    });
    Route::apiResource('trips', TripController::class);
    Route::apiResource('trip_bonus_rules', TripBonusRuleController::class);

    Route::get('debt-overview', [DebtOverviewController::class, 'index']);

    Route::prefix('dispatch')->group(function (): void {
        Route::get('board', [DispatchBoardController::class, 'board']);
        Route::get('unassigned-trips', [DispatchBoardController::class, 'unassignedTrips']);
        Route::get('daily-summary', [DispatchBoardController::class, 'dailySummary']);
    });

    Route::prefix('driver-schedules')->group(function (): void {
        Route::post('{driverWorkSchedule}/submit', [DriverScheduleController::class, 'submit'])->name('driver-schedules.submit');
        Route::post('{driverWorkSchedule}/approve', [DriverScheduleController::class, 'approve'])->name('driver-schedules.approve');
        Route::post('{driverWorkSchedule}/reject', [DriverScheduleController::class, 'reject'])->name('driver-schedules.reject');
        Route::post('{driverWorkSchedule}/lock', [DriverScheduleController::class, 'lock'])->name('driver-schedules.lock');
        Route::post('{driverWorkSchedule}/override', [DriverScheduleController::class, 'override'])->name('driver-schedules.override');
        Route::get('{driverWorkSchedule}/hos-check', [DriverScheduleController::class, 'hosCheck'])->name('driver-schedules.hos-check');
        Route::post('{driverWorkSchedule}/hos-check', [DriverScheduleController::class, 'hosCheck'])->name('driver-schedules.hos-check.post');
    });
    Route::apiResource('driver-schedules', DriverScheduleController::class);

    Route::prefix('attendances')->group(function (): void {
        Route::get('/', [AttendanceController::class, 'index'])->name('attendances.index');
        Route::post('check-in', [AttendanceController::class, 'checkIn'])->name('attendances.check-in');
        Route::post('check-out', [AttendanceController::class, 'checkOut'])->name('attendances.check-out');
        Route::patch('{id}/adjust', [AttendanceController::class, 'adjust'])->name('attendances.adjust');
        Route::get('late', [AttendanceController::class, 'late'])->name('attendances.late');
        Route::get('late/list', [AttendanceController::class, 'late'])->name('attendances.late.list');
        Route::post('late/notify', [AttendanceController::class, 'notifyLate'])->name('attendances.late.notify');
    });

    Route::prefix('leave')->group(function (): void {
        Route::get('types', [LeaveController::class, 'types'])->name('leave.types');
        Route::post('{leaveRequest}/approve', [LeaveController::class, 'approve'])->name('leave.approve');
        Route::post('{leaveRequest}/reject', [LeaveController::class, 'reject'])->name('leave.reject');
        Route::post('{leaveRequest}/cancel', [LeaveController::class, 'cancel'])->name('leave.cancel');
    });
    Route::apiResource('leave', LeaveController::class)->only(['index', 'store', 'show']);

    Route::prefix('overtime')->group(function (): void {
        Route::post('{overtimeRequest}/approve', [OvertimeController::class, 'approve'])->name('overtime.approve');
        Route::post('{overtimeRequest}/reject', [OvertimeController::class, 'reject'])->name('overtime.reject');
    });
    Route::apiResource('overtime', OvertimeController::class)->only(['index', 'store', 'show']);

    Route::prefix('violations')->group(function (): void {
        Route::post('{violation}/confirm', [ViolationController::class, 'confirm'])->name('violations.confirm');
        Route::post('{violation}/dispute', [ViolationController::class, 'dispute'])->name('violations.dispute');
        Route::post('{violation}/resolve-dispute', [ViolationController::class, 'resolveDispute'])->name('violations.resolve-dispute');
        Route::post('{violation}/waive', [ViolationController::class, 'waive'])->name('violations.waive');
    });
    Route::apiResource('violations', ViolationController::class)->only(['index', 'store', 'show']);

    Route::prefix('reports')->group(function (): void {
        Route::get('dashboard', [ReportsController::class, 'dashboard']);
        Route::get('payroll-summary', [ReportsController::class, 'payrollSummary']);
        Route::get('revenue-summary', [ReportsController::class, 'revenueSummary']);
        Route::get('revenue', [ReportsController::class, 'revenue']);
        Route::get('costs', [ReportsController::class, 'costs']);
        Route::get('profit', [ReportsController::class, 'profit']);
        Route::get('trips', [ReportsController::class, 'tripsReport']);
        Route::get('drivers', [ReportsController::class, 'driversReport']);
        Route::get('debt', [ReportsController::class, 'debt']);
        Route::get('maintenance', [ReportsController::class, 'maintenance']);
        Route::get('vehicle-performance', [ReportsController::class, 'vehiclePerformance']);
        Route::post('export', [ReportsController::class, 'export']);
        Route::get('exports/revenue', [ReportsController::class, 'exportRevenue'])->name('reports.exports.revenue');
        Route::get('exports/revenue-excel', [ReportsController::class, 'exportRevenueExcel'])->name('reports.exports.revenue_excel');
        Route::get('exports/trips', [ReportsController::class, 'exportTrips'])->name('reports.exports.trips');
        Route::get('exports/payroll', [ReportsController::class, 'exportPayroll'])->name('reports.exports.payroll');
    });
};
