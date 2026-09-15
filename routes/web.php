<?php

use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\CompanySettingsController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PlatformController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('dashboard'))->name('home');

Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'show'])->name('login');
    Route::post('login', [LoginController::class, 'store'])->name('login.store');

    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])->name('password.email');

    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('reset-password', [NewPasswordController::class, 'store'])->name('password.store');
});

Route::middleware('auth')->group(function () {
    Route::post('logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('users', [UserController::class, 'index'])->name('users.index');
    Route::get('users/create', [UserController::class, 'create'])->name('users.create');
    Route::post('users', [UserController::class, 'store'])->name('users.store');
    Route::get('users/{user}', [UserController::class, 'show'])->name('users.show');
    Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::delete('users/{user}/deactivate', [UserController::class, 'deactivate'])->name('users.deactivate');

    Route::get('branches', [BranchController::class, 'index'])->name('branches.index');
    Route::get('branches/create', [BranchController::class, 'create'])->name('branches.create');
    Route::post('branches', [BranchController::class, 'store'])->name('branches.store');
    Route::get('branches/{branch}', [BranchController::class, 'show'])->name('branches.show');
    Route::put('branches/{branch}', [BranchController::class, 'update'])->name('branches.update');
    Route::delete('branches/{branch}', [BranchController::class, 'destroy'])->name('branches.destroy');

    Route::get('settings/company', [CompanySettingsController::class, 'edit'])->name('settings.company.edit');
    Route::put('settings/company', [CompanySettingsController::class, 'update'])->name('settings.company.update');

    Route::get('settings/roles', [RoleController::class, 'index'])->name('settings.roles.index');
    Route::get('settings/roles/create', [RoleController::class, 'create'])->name('settings.roles.create');
    Route::post('settings/roles', [RoleController::class, 'store'])->name('settings.roles.store');
    Route::get('settings/roles/{role}', [RoleController::class, 'show'])->name('settings.roles.show');
    Route::get('settings/roles/{role}/edit', [RoleController::class, 'edit'])->name('settings.roles.edit');
    Route::put('settings/roles/{role}', [RoleController::class, 'update'])->name('settings.roles.update');
    Route::delete('settings/roles/{role}', [RoleController::class, 'destroy'])->name('settings.roles.destroy');

    Route::get('audit', [AuditLogController::class, 'index'])->name('audit.index');

    Route::get('platform', [PlatformController::class, 'index'])->name('platform.index')->middleware('can:tenants:manage');
});
