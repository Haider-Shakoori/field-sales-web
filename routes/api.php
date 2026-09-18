<?php

use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CollectionController;
use App\Http\Controllers\Api\CustomerCallController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ExpenseController;
use App\Http\Controllers\Api\GpsController;
use App\Http\Controllers\Api\MasterDataController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\TargetController;
use App\Http\Controllers\Api\TrackingController;
use App\Http\Controllers\Api\VisitController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/auth/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);

        Route::get('/attendance/today', [AttendanceController::class, 'today']);
        Route::get('/attendance/history', [AttendanceController::class, 'history']);
        Route::get('/gps/current', [GpsController::class, 'current']);
        Route::get('/gps/history', [GpsController::class, 'history']);
        Route::get('/settings/attendance-tracking', [SettingsController::class, 'show']);

        Route::get('/dashboard/summary', [DashboardController::class, 'summary']);
        Route::get('/tracking/live', [TrackingController::class, 'live']);
        Route::get('/tracking/{salesmanUuid}/route', [TrackingController::class, 'route']);
        Route::get('/tracking/{salesmanUuid}/gaps', [TrackingController::class, 'gaps']);

        Route::middleware('device.required')->group(function () {
            Route::post('/attendance/start', [AttendanceController::class, 'start']);
            Route::post('/attendance/end', [AttendanceController::class, 'end']);

            Route::post('/gps/locations', [GpsController::class, 'ingest']);
            Route::post('/gps/privacy-acknowledgement', [GpsController::class, 'acknowledge']);

            Route::get('/customers', [MasterDataController::class, 'customers']);
            Route::get('/territories', [MasterDataController::class, 'territories']);
            Route::get('/routes', [MasterDataController::class, 'routes']);
            Route::get('/routes/{route}/customers', [MasterDataController::class, 'routeCustomers']);
            Route::get('/products', [MasterDataController::class, 'products']);
            Route::get('/price-lists', [MasterDataController::class, 'priceLists']);

            Route::get('/visits/today', [VisitController::class, 'today']);
            Route::get('/visits/history', [VisitController::class, 'history']);
            Route::post('/visits/check-in', [VisitController::class, 'checkIn']);
            Route::post('/visits/{visit}/check-out', [VisitController::class, 'checkOut']);

            Route::post('/customer-calls', [CustomerCallController::class, 'store']);
            Route::get('/customers/{customerUuid}/activity', [CustomerCallController::class, 'activity']);

            Route::get('/orders', [OrderController::class, 'index']);
            Route::post('/orders', [OrderController::class, 'store']);
            Route::get('/orders/{order}', [OrderController::class, 'show']);

            Route::get('/collections', [CollectionController::class, 'index']);
            Route::post('/collections', [CollectionController::class, 'store']);

            Route::get('/expenses', [ExpenseController::class, 'index']);
            Route::post('/expenses', [ExpenseController::class, 'store']);

            Route::get('/targets/current', [TargetController::class, 'current']);

            Route::get('/notifications', [NotificationController::class, 'index']);
            Route::post('/notifications/{notification}/read', [NotificationController::class, 'read']);
            Route::post('/notifications/tracking-reminder', [NotificationController::class, 'trackingReminder']);
        });
    });
});
