<?php

use App\Http\Controllers\Api\V1\AttendanceController;
use App\Http\Controllers\Api\V1\AttendanceTrackingSettingsController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\DeviceController;
use App\Http\Controllers\Api\V1\GpsController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\PriceListController;
use App\Http\Controllers\Api\V1\PrivacyAcknowledgementController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\RouteController;
use App\Http\Controllers\Api\V1\SalesmanAssignmentController;
use App\Http\Controllers\Api\V1\SupervisorAssignmentController;
use App\Http\Controllers\Api\V1\TerritoryController;
use App\Http\Middleware\BootstrapTenantForAuth;
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

Route::post('v1/auth/login', [AuthController::class, 'login'])
    ->middleware(EnforceMinimumAppVersion::class)
    ->name('api.v1.auth.login');

Route::middleware([BootstrapTenantForAuth::class, 'auth:sanctum', InitializeTenancy::class])
    ->prefix('v1')
    ->name('api.v1.')
    ->group(function () {
        Route::get('me', [MeController::class, 'show'])->name('me');

        Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::post('auth/refresh', [AuthController::class, 'refresh'])->name('auth.refresh');

        Route::post('devices/register', [DeviceController::class, 'register'])->name('devices.register');

        Route::middleware([EnsureActiveDevice::class, EnforceMinimumAppVersion::class])
            ->group(function () {
                Route::put('devices/{device}/heartbeat', [DeviceController::class, 'heartbeat'])->name('devices.heartbeat');
                Route::delete('devices/{device}', [DeviceController::class, 'revoke'])->name('devices.revoke');

                Route::get('devices', [DeviceController::class, 'index'])->name('devices.index');

                // Customers
                Route::get('customers', [CustomerController::class, 'index'])->name('customers.index');
                Route::post('customers', [CustomerController::class, 'store'])->name('customers.store');
                Route::get('customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');
                Route::put('customers/{customer}', [CustomerController::class, 'update'])->name('customers.update');

                // Territories
                Route::get('territories', [TerritoryController::class, 'index'])->name('territories.index');
                Route::post('territories', [TerritoryController::class, 'store'])->name('territories.store');
                Route::get('territories/{territory}', [TerritoryController::class, 'show'])->name('territories.show');
                Route::put('territories/{territory}', [TerritoryController::class, 'update'])->name('territories.update');

                // Products
                Route::get('products', [ProductController::class, 'index'])->name('products.index');
                Route::get('products/{product}', [ProductController::class, 'show'])->name('products.show');

                // Price Lists
                Route::get('price-lists', [PriceListController::class, 'index'])->name('price-lists.index');

                // Routes
                Route::get('routes', [RouteController::class, 'index'])->name('routes.index');
                Route::post('routes', [RouteController::class, 'store'])->name('routes.store');
                Route::get('routes/{route}', [RouteController::class, 'show'])->name('routes.show');
                Route::put('routes/{route}', [RouteController::class, 'update'])->name('routes.update');
                Route::get('routes/{route}/customers', [RouteController::class, 'customers'])->name('routes.customers');
                Route::post('admin/routes/{route}/customers', [RouteController::class, 'addCustomer'])->name('routes.customers.store');

                // Salesman Assignments
                Route::get('admin/salesman-assignments', [SalesmanAssignmentController::class, 'index'])->name('salesman-assignments.index');
                Route::post('admin/salesman-assignments', [SalesmanAssignmentController::class, 'store'])->name('salesman-assignments.store');
                Route::get('admin/salesman-assignments/{assignment}', [SalesmanAssignmentController::class, 'show'])->name('salesman-assignments.show');
                Route::put('admin/salesman-assignments/{assignment}', [SalesmanAssignmentController::class, 'update'])->name('salesman-assignments.update');
                Route::delete('admin/salesman-assignments/{assignment}', [SalesmanAssignmentController::class, 'destroy'])->name('salesman-assignments.destroy');

                // Supervisor Assignments
                Route::get('admin/supervisor-assignments', [SupervisorAssignmentController::class, 'index'])->name('supervisor-assignments.index');
                Route::post('admin/supervisor-assignments', [SupervisorAssignmentController::class, 'store'])->name('supervisor-assignments.store');
                Route::get('admin/supervisor-assignments/{assignment}', [SupervisorAssignmentController::class, 'show'])->name('supervisor-assignments.show');
                Route::put('admin/supervisor-assignments/{assignment}', [SupervisorAssignmentController::class, 'update'])->name('supervisor-assignments.update');
                Route::delete('admin/supervisor-assignments/{assignment}', [SupervisorAssignmentController::class, 'destroy'])->name('supervisor-assignments.destroy');

                // Attendance / Work Sessions (Batch 7)
                Route::post('attendance/start', [AttendanceController::class, 'start'])
                    ->middleware('device.required')
                    ->name('attendance.start');
                Route::post('attendance/end', [AttendanceController::class, 'end'])
                    ->middleware('device.required')
                    ->name('attendance.end');
                Route::get('attendance/today', [AttendanceController::class, 'today'])->name('attendance.today');
                Route::get('attendance/history', [AttendanceController::class, 'history'])->name('attendance.history');

                // GPS Tracking (Batch 7)
                Route::post('gps/locations', [GpsController::class, 'store'])
                    ->middleware('device.required')
                    ->name('gps.locations.store');
                Route::get('gps/current', [GpsController::class, 'current'])->name('gps.current');
                Route::get('gps/history', [GpsController::class, 'history'])->name('gps.history');

                // Attendance & tracking policy (Batch 7 enhancement, read-only for mobile)
                Route::get('settings/attendance-tracking', [AttendanceTrackingSettingsController::class, 'show'])
                    ->middleware('device.required')
                    ->name('settings.attendance-tracking');

                Route::post('gps/privacy-acknowledgement', [PrivacyAcknowledgementController::class, 'store'])
                    ->middleware('device.required')
                    ->name('gps.privacy-acknowledgement');
            });
    });
