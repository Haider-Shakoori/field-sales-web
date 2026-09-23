<?php

use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CallActivityController;
use App\Http\Controllers\Api\CollectionController;
use App\Http\Controllers\Api\DailyRoutePlannerController;
use App\Http\Controllers\Api\ExpenseController;
use App\Http\Controllers\Api\GpsController;
use App\Http\Controllers\Api\MasterDataController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\SalesReturnController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\StockController;
use App\Http\Controllers\Api\TargetController;
use App\Http\Controllers\Api\VisitController;
use App\Http\Controllers\Api\VisitFormController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:api-login');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);

        Route::get('/attendance/today', [AttendanceController::class, 'today']);
        Route::get('/attendance/history', [AttendanceController::class, 'history']);
        Route::get('/gps/current', [GpsController::class, 'current']);
        Route::get('/gps/history', [GpsController::class, 'history']);
        Route::get('/settings/attendance-tracking', [SettingsController::class, 'show']);

        Route::middleware('device.required')->group(function () {
            Route::get('/auth/me', [AuthController::class, 'me']);

            Route::get('/customers', [MasterDataController::class, 'customers']);
            Route::post('/customers', [MasterDataController::class, 'storeCustomer']);
            Route::get('/territories', [MasterDataController::class, 'territories']);
            Route::get('/routes', [MasterDataController::class, 'routes']);
            Route::get('/routes/{route:uuid}/customers', [MasterDataController::class, 'routeCustomers']);
            Route::get('/route-plan/today', [DailyRoutePlannerController::class, 'today']);
            Route::get('/products', [MasterDataController::class, 'products']);
            Route::get('/price-lists', [MasterDataController::class, 'priceLists']);
            Route::get('/price-lists/{priceList:uuid}/items', [MasterDataController::class, 'priceListItems']);

            Route::post('/attendance/start', [AttendanceController::class, 'start']);
            Route::post('/attendance/end', [AttendanceController::class, 'end']);
            Route::post('/gps/locations', [GpsController::class, 'ingest']);
            Route::post('/gps/privacy-acknowledgement', [GpsController::class, 'acknowledge']);

            Route::get('/visit-forms', [VisitFormController::class, 'index']);

            Route::get('/visits/today', [VisitController::class, 'today']);
            Route::get('/visits/history', [VisitController::class, 'history']);
            Route::post('/visits/check-in', [VisitController::class, 'checkIn']);
            Route::post('/visits/{visit:uuid}/check-out', [VisitController::class, 'checkOut']);
            Route::post('/visits/{visit:uuid}/photos', [VisitController::class, 'uploadPhoto']);
            Route::post('/visits/{visit:uuid}/form-submissions', [VisitFormController::class, 'store']);

            Route::get('/call-activities/history', [CallActivityController::class, 'history']);
            Route::post('/call-activities', [CallActivityController::class, 'store']);

            Route::get('/stock/me', [StockController::class, 'mine']);
            Route::post('/returns', [SalesReturnController::class, 'store']);
            Route::get('/returns/history', [SalesReturnController::class, 'history']);

            Route::get('/orders/history', [OrderController::class, 'history']);
            Route::get('/orders/{order:uuid}', [OrderController::class, 'show']);
            Route::post('/orders', [OrderController::class, 'store']);

            Route::get('/collections/balances', [CollectionController::class, 'balances']);
            Route::get('/collections/history', [CollectionController::class, 'history']);
            Route::get('/collections/{collection:uuid}', [CollectionController::class, 'show']);
            Route::post('/collections', [CollectionController::class, 'store']);

            Route::get('/expenses/history', [ExpenseController::class, 'history']);
            Route::get('/expenses/{expense:uuid}', [ExpenseController::class, 'show']);
            Route::post('/expenses', [ExpenseController::class, 'store']);

            Route::get('/targets/current', [TargetController::class, 'current']);
            Route::get('/targets/history', [TargetController::class, 'history']);
            Route::get('/notifications', [NotificationController::class, 'index']);
            Route::patch('/notifications/{notification:uuid}/read', [NotificationController::class, 'read']);
            Route::get('/notification-preferences', [NotificationController::class, 'preferences']);
            Route::put('/notification-preferences', [NotificationController::class, 'updatePreferences']);
        });
    });
});
