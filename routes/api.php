<?php

use App\Http\Controllers\Api\V1\MeController;
use App\Http\Middleware\InitializeTenancy;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'app' => 'Field Sales API',
        'time' => now('UTC')->toIso8601String(),
    ]);
});

Route::middleware(['auth:sanctum', InitializeTenancy::class])
    ->prefix('v1')
    ->name('api.v1.')
    ->group(function () {
        Route::get('me', [MeController::class, 'show'])->name('me');
    });
