<?php

use App\Http\Controllers\Web\AdminOperationsController;
use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\TrackingSettingsController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin/dashboard');
Route::get('/login', [AuthController::class, 'create'])->name('login');
Route::post('/login', [AuthController::class, 'store']);

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');

    Route::get('/admin/dashboard', [AdminOperationsController::class, 'dashboard'])
        ->middleware('permission:reports:view')
        ->name('admin.dashboard');

    Route::get('/admin/attendance', [AdminOperationsController::class, 'attendance'])
        ->middleware('permission:attendance:view')
        ->name('admin.attendance');

    Route::get('/admin/current-locations', [AdminOperationsController::class, 'locations'])
        ->middleware('permission:tracking:view')
        ->name('admin.locations');

    Route::get('/admin/customers', [AdminOperationsController::class, 'customers'])
        ->middleware('permission:customers:view')
        ->name('admin.customers');

    Route::get('/admin/visits', [AdminOperationsController::class, 'visits'])
        ->middleware('permission:visits:view')
        ->name('admin.visits');

    Route::get('/admin/orders', [AdminOperationsController::class, 'orders'])
        ->middleware('permission:orders:view')
        ->name('admin.orders');

    Route::get('/admin/collections', [AdminOperationsController::class, 'collections'])
        ->middleware('permission:collections:view')
        ->name('admin.collections');

    Route::get('/admin/expenses', [AdminOperationsController::class, 'expenses'])
        ->middleware('permission:expenses:view')
        ->name('admin.expenses');

    Route::get('/admin/targets', [AdminOperationsController::class, 'targets'])
        ->middleware('permission:targets:view')
        ->name('admin.targets');

    Route::get('/admin/tracking-settings', [TrackingSettingsController::class, 'edit'])
        ->middleware('permission:settings:view')
        ->name('tracking.edit');

    Route::put('/admin/tracking-settings', [TrackingSettingsController::class, 'update'])
        ->middleware('permission:settings:manage')
        ->name('tracking.update');
});
