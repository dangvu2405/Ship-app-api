<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\Interface\Http\Controllers\Employee\EmployeeController;

/*
|--------------------------------------------------------------------------
| Clean Architecture API Routes
|--------------------------------------------------------------------------
|
| These routes follow Clean Architecture + DDD patterns.
| All routes use controllers from src/Interface/Http/Controllers/
|
*/

Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {

    // Employee Module (Clean Architecture)
    Route::prefix('v2/employees')->group(function () {
        Route::get('/', [EmployeeController::class, 'index']);
        Route::post('/', [EmployeeController::class, 'store']);
        Route::get('/{employee}', [EmployeeController::class, 'show']);
        Route::put('/{employee}', [EmployeeController::class, 'update']);
        Route::delete('/{employee}', [EmployeeController::class, 'destroy']);
    });

});
