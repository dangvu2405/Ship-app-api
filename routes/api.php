<?php

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

// Public routes
Route::get('/health', function () {
    return response()->json([
        'success' => true,
        'message' => 'API is running',
        'timestamp' => now()->toDateTimeString(),
    ]);
});

// Authentication routes (public)
Route::prefix('auth')->group(function () {
    Route::post('/login', [\App\Http\Controllers\Api\AuthController::class, 'login']);
    Route::get('/test-accounts', [\App\Http\Controllers\Api\AuthController::class, 'testAccounts']);
});

// Token protected routes (All users)
Route::middleware(['auth:sanctum'])->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('/logout', [\App\Http\Controllers\Api\AuthController::class, 'logout']);
        Route::post('/refresh', [\App\Http\Controllers\Api\AuthController::class, 'refresh']);
    });

    Route::get('/user', function (Request $request) {
        $user = $request->user();
        $user->load(['employee', 'roles.permissions']);

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $user,
        ]);
    });

    Route::get('payrolls/my-salary', [\App\Http\Controllers\Api\PayrollController::class, 'mySalary']);
});

// Protected routes: RBAC enabled
Route::middleware(['auth:sanctum'])->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('/register', [\App\Http\Controllers\Api\AuthController::class, 'register'])
            ->middleware('role:admin');
    });

    // Organization Setup
    Route::apiResource('companies', \App\Http\Controllers\Api\CompanyController::class)->middleware('permission:companies');
    Route::apiResource('offices', \App\Http\Controllers\Api\OfficeController::class)->middleware('permission:offices');
    Route::apiResource('departments', \App\Http\Controllers\Api\DepartmentController::class)->middleware('permission:departments');
    Route::apiResource('positions', \App\Http\Controllers\Api\PositionController::class)->middleware('permission:positions');
    
    // Human Resources
    Route::apiResource('employees', \App\Http\Controllers\Api\EmployeeController::class)->middleware('permission:employees');
    Route::apiResource('attendances', \App\Http\Controllers\Api\AttendanceController::class)->middleware('permission:attendances');
    Route::apiResource('allowances', \App\Http\Controllers\Api\AllowanceController::class)->middleware('permission:allowances');
    Route::apiResource('deductions', \App\Http\Controllers\Api\DeductionController::class)->middleware('permission:deductions');

    // Payroll Operations
    Route::post('payrolls/{id}/approve', [\App\Http\Controllers\Api\PayrollController::class, 'approve'])
        ->middleware('permission:payrolls')
        ->name('payrolls.approve');
    Route::post('payrolls/{id}/lock', [\App\Http\Controllers\Api\PayrollController::class, 'lock'])
        ->middleware('permission:payrolls')
        ->name('payrolls.lock');
    Route::get('payrolls/{id}/export', [\App\Http\Controllers\Api\PayrollController::class, 'export'])
        ->middleware('permission:payrolls')
        ->name('payrolls.export');
    Route::apiResource('payrolls', \App\Http\Controllers\Api\PayrollController::class)->middleware('permission:payrolls');

    // Fleet & Fleet Operations
    Route::apiResource('drivers', \App\Http\Controllers\Api\DriverController::class)->middleware('permission:drivers');
    Route::apiResource('vehicles', \App\Http\Controllers\Api\VehicleController::class)->middleware('permission:vehicles');
    Route::apiResource('vehicle_assignments', \App\Http\Controllers\Api\VehicleAssignmentController::class)->middleware('permission:vehicle_assignments');
    Route::apiResource('vehicle_expenses', \App\Http\Controllers\Api\VehicleExpenseController::class)->middleware('permission:vehicle_expenses');
    Route::apiResource('customers', \App\Http\Controllers\Api\CustomerController::class)->middleware('permission:customers');
    Route::apiResource('trips', \App\Http\Controllers\Api\TripController::class)->middleware('permission:trips');
    Route::apiResource('invoices', \App\Http\Controllers\Api\InvoiceController::class)->middleware('permission:invoices');

    // System Administration
    Route::apiResource('users', \App\Http\Controllers\Api\UserController::class)->middleware('permission:users');
    Route::apiResource('roles', \App\Http\Controllers\Api\RoleController::class)->middleware('permission:roles');
    Route::post('roles/{role}/permissions', [\App\Http\Controllers\Api\RoleController::class, 'syncPermissions'])
        ->middleware('permission:roles')
        ->name('roles.permissions');
    Route::get('permissions', [\App\Http\Controllers\Api\PermissionController::class, 'index'])->middleware('permission:permissions');
    Route::get('permissions/{permission}', [\App\Http\Controllers\Api\PermissionController::class, 'show'])->middleware('permission:permissions');

    // Reports & Dashboard
    Route::get('dashboard/stats', [\App\Http\Controllers\Api\ReportsController::class, 'dashboardStats']);
    Route::get('reports/dashboard', [\App\Http\Controllers\Api\ReportsController::class, 'dashboard'])->middleware('permission:reports');
    Route::get('reports/payroll-summary', [\App\Http\Controllers\Api\ReportsController::class, 'payrollSummary'])->middleware('permission:reports');
});
