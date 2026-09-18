<?php

use App\Http\Controllers\Web\AuditLogController;
use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\BranchController;
use App\Http\Controllers\Web\RoleController;
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
            ->middleware([
                'index' => 'permission:users:view',
                'create' => 'permission:users:manage',
                'store' => 'permission:users:manage',
                'edit' => 'permission:users:manage',
                'update' => 'permission:users:manage',
                'destroy' => 'permission:users:manage',
            ]);

        Route::resource('roles', RoleController::class)
            ->except(['show'])
            ->middleware([
                'index' => 'permission:roles:view',
                'create' => 'permission:roles:manage',
                'store' => 'permission:roles:manage',
                'edit' => 'permission:roles:manage',
                'update' => 'permission:roles:manage',
                'destroy' => 'permission:roles:manage',
            ]);

        Route::resource('branches', BranchController::class)
            ->except(['show'])
            ->middleware([
                'index' => 'permission:branches:view',
                'create' => 'permission:branches:manage',
                'store' => 'permission:branches:manage',
                'edit' => 'permission:branches:manage',
                'update' => 'permission:branches:manage',
                'destroy' => 'permission:branches:manage',
            ]);

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
