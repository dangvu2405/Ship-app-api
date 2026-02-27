<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

// Backend API - No web routes needed
Route::get('/', function () {
    return response()->json([
        'name' => config('app.name'),
        'version' => '2.0.0',
        'documentation' => '/api/documentation',
    ]);
});
