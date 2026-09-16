<?php

use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\CompanySettingsController;
use App\Http\Controllers\CustomerCategoryController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeviceController;
use App\Http\Controllers\PlatformController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\RouteController;
use App\Http\Controllers\SalesmanAssignmentController;
use App\Http\Controllers\SalesmanController;
use App\Http\Controllers\SupervisorAssignmentController;
use App\Http\Controllers\SupervisorController;
use App\Http\Controllers\TerritoryController;
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

    Route::get('salesmen', [SalesmanController::class, 'index'])->name('salesmen.index');
    Route::get('salesmen/create', [SalesmanController::class, 'create'])->name('salesmen.create');
    Route::post('salesmen', [SalesmanController::class, 'store'])->name('salesmen.store');
    Route::get('salesmen/{salesman}', [SalesmanController::class, 'show'])->name('salesmen.show');
    Route::put('salesmen/{salesman}', [SalesmanController::class, 'update'])->name('salesmen.update');
    Route::delete('salesmen/{salesman}/deactivate', [SalesmanController::class, 'deactivate'])->name('salesmen.deactivate');

    Route::get('supervisors', [SupervisorController::class, 'index'])->name('supervisors.index');
    Route::get('supervisors/create', [SupervisorController::class, 'create'])->name('supervisors.create');
    Route::post('supervisors', [SupervisorController::class, 'store'])->name('supervisors.store');
    Route::get('supervisors/{supervisor}', [SupervisorController::class, 'show'])->name('supervisors.show');
    Route::put('supervisors/{supervisor}', [SupervisorController::class, 'update'])->name('supervisors.update');
    Route::delete('supervisors/{supervisor}/deactivate', [SupervisorController::class, 'deactivate'])->name('supervisors.deactivate');

    // Salesman Assignments
    Route::get('salesman-assignments', [SalesmanAssignmentController::class, 'index'])->name('salesman-assignments.index');
    Route::get('salesman-assignments/create', [SalesmanAssignmentController::class, 'create'])->name('salesman-assignments.create');
    Route::post('salesman-assignments', [SalesmanAssignmentController::class, 'store'])->name('salesman-assignments.store');
    Route::get('salesman-assignments/{assignment}', [SalesmanAssignmentController::class, 'show'])->name('salesman-assignments.show');
    Route::get('salesman-assignments/{assignment}/edit', [SalesmanAssignmentController::class, 'edit'])->name('salesman-assignments.edit');
    Route::put('salesman-assignments/{assignment}', [SalesmanAssignmentController::class, 'update'])->name('salesman-assignments.update');
    Route::delete('salesman-assignments/{assignment}', [SalesmanAssignmentController::class, 'destroy'])->name('salesman-assignments.destroy');

    // Supervisor Assignments
    Route::get('supervisor-assignments', [SupervisorAssignmentController::class, 'index'])->name('supervisor-assignments.index');
    Route::get('supervisor-assignments/create', [SupervisorAssignmentController::class, 'create'])->name('supervisor-assignments.create');
    Route::post('supervisor-assignments', [SupervisorAssignmentController::class, 'store'])->name('supervisor-assignments.store');
    Route::get('supervisor-assignments/{assignment}', [SupervisorAssignmentController::class, 'show'])->name('supervisor-assignments.show');
    Route::get('supervisor-assignments/{assignment}/edit', [SupervisorAssignmentController::class, 'edit'])->name('supervisor-assignments.edit');
    Route::put('supervisor-assignments/{assignment}', [SupervisorAssignmentController::class, 'update'])->name('supervisor-assignments.update');
    Route::delete('supervisor-assignments/{assignment}', [SupervisorAssignmentController::class, 'destroy'])->name('supervisor-assignments.destroy');

    Route::get('devices', [DeviceController::class, 'index'])->name('devices.index');
    Route::get('devices/{device}', [DeviceController::class, 'show'])->name('devices.show');
    Route::delete('devices/{device}/revoke', [DeviceController::class, 'revoke'])->name('devices.revoke');

    Route::get('branches', [BranchController::class, 'index'])->name('branches.index');
    Route::get('branches/create', [BranchController::class, 'create'])->name('branches.create');
    Route::post('branches', [BranchController::class, 'store'])->name('branches.store');
    Route::get('branches/{branch}', [BranchController::class, 'show'])->name('branches.show');
    Route::put('branches/{branch}', [BranchController::class, 'update'])->name('branches.update');
    Route::delete('branches/{branch}', [BranchController::class, 'destroy'])->name('branches.destroy');

    // Customer Categories
    Route::get('customer-categories', [CustomerCategoryController::class, 'index'])->name('customer-categories.index');
    Route::get('customer-categories/create', [CustomerCategoryController::class, 'create'])->name('customer-categories.create');
    Route::post('customer-categories', [CustomerCategoryController::class, 'store'])->name('customer-categories.store');
    Route::get('customer-categories/{category}', [CustomerCategoryController::class, 'show'])->name('customer-categories.show');
    Route::get('customer-categories/{category}/edit', [CustomerCategoryController::class, 'edit'])->name('customer-categories.edit');
    Route::put('customer-categories/{category}', [CustomerCategoryController::class, 'update'])->name('customer-categories.update');
    Route::delete('customer-categories/{category}', [CustomerCategoryController::class, 'destroy'])->name('customer-categories.destroy');
    Route::delete('customer-categories/{category}/deactivate', [CustomerCategoryController::class, 'deactivate'])->name('customer-categories.deactivate');

    // Territories
    Route::get('territories', [TerritoryController::class, 'index'])->name('territories.index');
    Route::get('territories/create', [TerritoryController::class, 'create'])->name('territories.create');
    Route::post('territories', [TerritoryController::class, 'store'])->name('territories.store');
    Route::get('territories/{territory}', [TerritoryController::class, 'show'])->name('territories.show');
    Route::get('territories/{territory}/edit', [TerritoryController::class, 'edit'])->name('territories.edit');
    Route::put('territories/{territory}', [TerritoryController::class, 'update'])->name('territories.update');
    Route::delete('territories/{territory}/deactivate', [TerritoryController::class, 'deactivate'])->name('territories.deactivate');

    // Routes
    Route::get('routes', [RouteController::class, 'index'])->name('routes.index');
    Route::get('routes/create', [RouteController::class, 'create'])->name('routes.create');
    Route::post('routes', [RouteController::class, 'store'])->name('routes.store');
    Route::get('routes/{route}', [RouteController::class, 'show'])->name('routes.show');
    Route::get('routes/{route}/edit', [RouteController::class, 'edit'])->name('routes.edit');
    Route::put('routes/{route}', [RouteController::class, 'update'])->name('routes.update');
    Route::delete('routes/{route}/deactivate', [RouteController::class, 'deactivate'])->name('routes.deactivate');
    Route::post('routes/{route}/customers', [RouteController::class, 'addCustomer'])->name('routes.customers.add');
    Route::put('routes/{route}/customers/{route_customer}', [RouteController::class, 'updateCustomer'])->name('routes.customers.update');
    Route::delete('routes/{route}/customers/{route_customer}', [RouteController::class, 'removeCustomer'])->name('routes.customers.remove');

    // Customers
    Route::get('customers', [CustomerController::class, 'index'])->name('customers.index');
    Route::get('customers/create', [CustomerController::class, 'create'])->name('customers.create');
    Route::post('customers', [CustomerController::class, 'store'])->name('customers.store');
    Route::get('customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');
    Route::get('customers/{customer}/edit', [CustomerController::class, 'edit'])->name('customers.edit');
    Route::put('customers/{customer}', [CustomerController::class, 'update'])->name('customers.update');
    Route::delete('customers/{customer}/deactivate', [CustomerController::class, 'deactivate'])->name('customers.deactivate');

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
