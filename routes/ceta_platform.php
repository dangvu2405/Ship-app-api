<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AiAdvisorController;
use App\Http\Controllers\Api\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/**
 * CETA platform-only surface (global admin / legacy extras).
 * Registered under /api with role:admin.
 */
return static function (): void {
    Route::prefix('auth')->group(function (): void {
        Route::post('/register', [AuthController::class, 'register']);
    });

    Route::post('ai/business-assist', [AiAdvisorController::class, 'businessAssist']);

    Route::get('documentation', static function () {
        return response()->json([
            'success' => true,
            'message' => 'API documentation endpoint',
            'deprecated' => true,
            'data' => [
                'swagger_ui' => url('/api/documentation'),
                'openapi_json' => url('/docs?format=openapi'),
            ],
        ]);
    });

    Route::get('employees', static function (Request $request) {
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
            'deprecated' => true,
            'data' => $drivers,
        ]);
    });

    Route::get('allowances', static function () {
        return response()->json([
            'success' => true,
            'message' => 'Legacy endpoint retained for compatibility',
            'deprecated' => true,
            'data' => [],
        ]);
    });

    Route::get('deductions', static function () {
        return response()->json([
            'success' => true,
            'message' => 'Legacy endpoint retained for compatibility',
            'deprecated' => true,
            'data' => [],
        ]);
    });
};
