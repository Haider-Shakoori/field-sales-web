<?php

use App\Http\Controllers\Api\V1\DeviceController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Middleware\EnforceMinimumAppVersion;
use App\Http\Middleware\EnsureActiveDevice;
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

        Route::post('devices/register', [DeviceController::class, 'register'])->name('devices.register');

        Route::middleware([EnsureActiveDevice::class, EnforceMinimumAppVersion::class])
            ->group(function () {
                Route::put('devices/{device}/heartbeat', [DeviceController::class, 'heartbeat'])->name('devices.heartbeat');
                Route::delete('devices/{device}', [DeviceController::class, 'revoke'])->name('devices.revoke');

                Route::get('devices', [DeviceController::class, 'index'])->name('devices.index');
            });
    });
