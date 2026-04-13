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
        'data' => $user,
    ]);
};

$registerAuthenticatedRoutes = static function () use ($currentUserResponse): void {
    Route::prefix('auth')->group(function () use ($currentUserResponse): void {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
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
    Route::post('ai/business-assist', [\App\Http\Controllers\Api\AiAdvisorController::class, 'businessAssist']);
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
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/social/login', [AuthController::class, 'socialLogin']);
});

$larkWebhookController = 'App\\Http\\Controllers\\Api\\LarkWebhookController';
$larkAuthController = 'App\\Http\\Controllers\\Api\\LarkAuthController';

if (class_exists($larkWebhookController)) {
    // Lark webhook/event entrypoint
    Route::post('/lark/webhook', [$larkWebhookController, 'handle']);
}

// Protected routes: authenticated users (legacy)
Route::middleware(['auth:sanctum'])->group($registerAuthenticatedRoutes);

// Protected routes: admin only (legacy)
Route::middleware(['auth:sanctum', 'role:admin'])->group($registerAdminRoutes);

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
        Route::post('/social/login', [AuthController::class, 'socialLogin']);
    });

    Route::middleware(['auth:sanctum'])->group($registerAuthenticatedRoutes);
    Route::middleware(['auth:sanctum', 'role:admin'])->group($registerAdminRoutes);
});

if (class_exists($larkAuthController)) {
    // Backward-compatible OAuth callback alias
    Route::match(['get', 'post'], '/callback/lark', [$larkAuthController, 'callback']);
}
