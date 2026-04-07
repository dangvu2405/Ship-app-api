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
Route::get('/', function () {
    return response()->json([
        'success' => true,
        'message' => 'Company Ship API',
        'version' => 'v1',
    ]);
});

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
});

// Protected routes: authenticated users
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

// Protected routes: admin only
Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('/register', [\App\Http\Controllers\Api\AuthController::class, 'register']);
    });

    Route::apiResource('companies', \App\Http\Controllers\Api\CompanyController::class);
    Route::apiResource('offices', \App\Http\Controllers\Api\OfficeController::class);
    Route::apiResource('departments', \App\Http\Controllers\Api\DepartmentController::class);
    Route::apiResource('positions', \App\Http\Controllers\Api\PositionController::class);
    Route::apiResource('employees', \App\Http\Controllers\Api\EmployeeController::class);
    Route::apiResource('drivers', \App\Http\Controllers\Api\DriverController::class);
    Route::apiResource('vehicles', \App\Http\Controllers\Api\VehicleController::class);
    Route::apiResource('vehicle_assignments', \App\Http\Controllers\Api\VehicleAssignmentController::class);
    Route::apiResource('vehicle_expenses', \App\Http\Controllers\Api\VehicleExpenseController::class);
    Route::apiResource('customers', \App\Http\Controllers\Api\CustomerController::class);
    Route::apiResource('trips', \App\Http\Controllers\Api\TripController::class);
    Route::apiResource('trip_bonus_rules', \App\Http\Controllers\Api\TripBonusRuleController::class);
    Route::apiResource('invoices', \App\Http\Controllers\Api\InvoiceController::class);
    Route::apiResource('allowances', \App\Http\Controllers\Api\AllowanceController::class);
    Route::apiResource('deductions', \App\Http\Controllers\Api\DeductionController::class);
    Route::apiResource('attendances', \App\Http\Controllers\Api\AttendanceController::class);

    Route::post('payrolls/{id}/approve', [\App\Http\Controllers\Api\PayrollController::class, 'approve'])->name('payrolls.approve');
    Route::post('payrolls/{id}/lock', [\App\Http\Controllers\Api\PayrollController::class, 'lock'])->name('payrolls.lock');
    Route::get('payrolls/{id}/export', [\App\Http\Controllers\Api\PayrollController::class, 'export'])->name('payrolls.export');
    Route::apiResource('payrolls', \App\Http\Controllers\Api\PayrollController::class);

    Route::apiResource('users', \App\Http\Controllers\Api\UserController::class);
    Route::post('roles/{role}/permissions', [\App\Http\Controllers\Api\RoleController::class, 'syncPermissions'])->name('roles.permissions');
    Route::apiResource('roles', \App\Http\Controllers\Api\RoleController::class);
    Route::get('permissions', [\App\Http\Controllers\Api\PermissionController::class, 'index']);
    Route::get('permissions/{permission}', [\App\Http\Controllers\Api\PermissionController::class, 'show']);

    Route::get('reports/dashboard', [\App\Http\Controllers\Api\ReportsController::class, 'dashboard']);
    Route::get('reports/payroll-summary', [\App\Http\Controllers\Api\ReportsController::class, 'payrollSummary']);
});
