<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Company\CompanyController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\PayrollAdjustmentController;
use App\Http\Controllers\Api\PayrollController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\User\UserController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/**
 * CETA "Accountant+" / company back-office (company_admin and above).
 */
return static function (): void {
    $has = static function (string $table): bool {
        try {
            return Schema::hasTable($table);
        } catch (\Throwable) {
            return false;
        }
    };

    Route::apiResource('companies', CompanyController::class);

    Route::patch('users/{user}/status', [UserController::class, 'patchStatus']);
    Route::get('users/{user}/permissions', [UserController::class, 'permissions']);
    Route::put('users/{user}/permissions', [UserController::class, 'syncPermissions']);
    Route::post('users/{user}/reset-password', [UserController::class, 'resetPassword']);
    Route::apiResource('users', UserController::class);

    if ($has('roles')) {
        Route::post('roles/{role}/permissions', [RoleController::class, 'syncPermissions'])->name('roles.permissions');
        Route::apiResource('roles', RoleController::class);
    }
    if ($has('permissions')) {
        Route::get('permissions', [PermissionController::class, 'index']);
        Route::get('permissions/{permission}', [PermissionController::class, 'show']);
    }

    Route::prefix('invoices')->group(function (): void {
        Route::post('{id}/issue', [InvoiceController::class, 'issue'])->name('invoices.issue');
        Route::post('{id}/mark-paid', [InvoiceController::class, 'markPaid'])->name('invoices.mark-paid');
        Route::post('{id}/send-cqt', [InvoiceController::class, 'sendCqt'])->name('invoices.send-cqt');
        Route::post('{id}/cancel', [InvoiceController::class, 'cancel'])->name('invoices.cancel');
    });
    Route::apiResource('invoices', InvoiceController::class);

    Route::prefix('payroll-adjustments')->group(function (): void {
        Route::post('{id}/approve', [PayrollAdjustmentController::class, 'approve'])->name('payroll-adjustments.approve');
        Route::post('{id}/reject', [PayrollAdjustmentController::class, 'reject'])->name('payroll-adjustments.reject');
        Route::get('/', [PayrollAdjustmentController::class, 'index'])->name('payroll-adjustments.index');
        Route::post('/', [PayrollAdjustmentController::class, 'store'])->name('payroll-adjustments.store');
        Route::get('{id}', [PayrollAdjustmentController::class, 'show'])->name('payroll-adjustments.show');
        Route::put('{id}', [PayrollAdjustmentController::class, 'update'])->name('payroll-adjustments.update');
        Route::patch('{id}', [PayrollAdjustmentController::class, 'update'])->name('payroll-adjustments.patch');
        Route::delete('{id}', [PayrollAdjustmentController::class, 'destroy'])->name('payroll-adjustments.destroy');
    });

    Route::prefix('payrolls')->group(function (): void {
        Route::post('{id}/approve', [PayrollController::class, 'approve'])->name('payrolls.approve');
        Route::post('{id}/lock', [PayrollController::class, 'lock'])->name('payrolls.lock');
        Route::post('{id}/mark-paid', [PayrollController::class, 'markPaid'])->name('payrolls.mark-paid');
        Route::get('{id}/export', [PayrollController::class, 'export'])->name('payrolls.export');
        Route::get('driver/{driverId}', [PayrollController::class, 'driverMonthlySalary'])->name('payrolls.driver-monthly');
    });
    Route::apiResource('payrolls', PayrollController::class);
};
