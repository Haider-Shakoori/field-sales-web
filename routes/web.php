<?php

use App\Http\Controllers\Web\AuditLogController;
use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\BranchController;
use App\Http\Controllers\Web\CustomerController;
use App\Http\Controllers\Web\DeviceController;
use App\Http\Controllers\Web\RoleController;
use App\Http\Controllers\Web\RouteCustomerController;
use App\Http\Controllers\Web\SalesRouteController;
use App\Http\Controllers\Web\SalesmanAssignmentController;
use App\Http\Controllers\Web\SalesmanController;
use App\Http\Controllers\Web\SupervisorAssignmentController;
use App\Http\Controllers\Web\SupervisorController;
use App\Http\Controllers\Web\TerritoryController;
use App\Http\Controllers\Web\TrackingSettingsController;
use App\Http\Controllers\Web\UserController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin/users');

Route::get('/login', [AuthController::class, 'create'])->name('login');
Route::post('/login', [AuthController::class, 'store']);

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');

    Route::prefix('admin')->name('admin.')->group(function () {
        Route::resource('users', UserController::class)
            ->except(['show'])
            ->middlewareFor('index', 'permission:users:view')
            ->middlewareFor(['create', 'store', 'edit', 'update', 'destroy'], 'permission:users:manage');

        Route::resource('roles', RoleController::class)
            ->except(['show'])
            ->middlewareFor('index', 'permission:roles:view')
            ->middlewareFor(['create', 'store', 'edit', 'update', 'destroy'], 'permission:roles:manage');

        Route::resource('branches', BranchController::class)
            ->except(['show'])
            ->middlewareFor('index', 'permission:branches:view')
            ->middlewareFor(['create', 'store', 'edit', 'update', 'destroy'], 'permission:branches:manage');

        Route::resource('salesmen', SalesmanController::class)
            ->middlewareFor(['index', 'show'], 'permission:sales-team:view')
            ->middlewareFor(['create', 'store', 'edit', 'update', 'destroy'], 'permission:sales-team:manage');

        Route::resource('supervisors', SupervisorController::class)
            ->middlewareFor(['index', 'show'], 'permission:sales-team:view')
            ->middlewareFor(['create', 'store', 'edit', 'update', 'destroy'], 'permission:sales-team:manage');

        Route::get('/devices', [DeviceController::class, 'index'])
            ->middleware('permission:sales-team:view')
            ->name('devices.index');
        Route::get('/devices/{device}', [DeviceController::class, 'show'])
            ->middleware('permission:sales-team:view')
            ->name('devices.show');
        Route::post('/devices/{device}/revoke', [DeviceController::class, 'revoke'])
            ->middleware('permission:sales-team:manage')
            ->name('devices.revoke');

        Route::resource('salesman-assignments', SalesmanAssignmentController::class)
            ->parameters(['salesman-assignments' => 'assignment'])
            ->middlewareFor(['index', 'show'], 'permission:sales-team:view')
            ->middlewareFor(['create', 'store', 'edit', 'update', 'destroy'], 'permission:sales-team:manage');

        Route::resource('supervisor-assignments', SupervisorAssignmentController::class)
            ->parameters(['supervisor-assignments' => 'assignment'])
            ->middlewareFor(['index', 'show'], 'permission:sales-team:view')
            ->middlewareFor(['create', 'store', 'edit', 'update', 'destroy'], 'permission:sales-team:manage');

        Route::resource('territories', TerritoryController::class)
            ->middlewareFor(['index', 'show'], 'permission:customers:view')
            ->middlewareFor(['create', 'store', 'edit', 'update', 'destroy'], 'permission:customers:manage');

        Route::resource('customers', CustomerController::class)
            ->middlewareFor(['index', 'show'], 'permission:customers:view')
            ->middlewareFor(['create', 'store', 'edit', 'update', 'destroy'], 'permission:customers:manage');

        Route::resource('routes', SalesRouteController::class)
            ->middlewareFor(['index', 'show'], 'permission:customers:view')
            ->middlewareFor(['create', 'store', 'edit', 'update', 'destroy'], 'permission:customers:manage');

        Route::post('/routes/{route}/customers', [RouteCustomerController::class, 'store'])
            ->middleware('permission:customers:manage')
            ->name('routes.customers.store');
        Route::patch('/routes/{route}/customers/reorder', [RouteCustomerController::class, 'reorder'])
            ->middleware('permission:customers:manage')
            ->name('routes.customers.reorder');
        Route::delete('/routes/{route}/customers/{routeCustomer}', [RouteCustomerController::class, 'destroy'])
            ->middleware('permission:customers:manage')
            ->name('routes.customers.destroy');

        Route::get('/audit-logs', [AuditLogController::class, 'index'])
            ->middleware('permission:audit:view')
            ->name('audit.index');
    });

    Route::get('/admin/tracking-settings', [TrackingSettingsController::class, 'edit'])
        ->middleware('permission:settings:view')
        ->name('tracking.edit');

    Route::put('/admin/tracking-settings', [TrackingSettingsController::class, 'update'])
        ->middleware('permission:settings:manage')
        ->name('tracking.update');
});
