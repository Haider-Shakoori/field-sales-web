<?php
use App\Http\Controllers\Api\{AttendanceController,AuthController,GpsController,SettingsController}; use Illuminate\Support\Facades\Route;
Route::prefix('v1')->group(function(){
 Route::post('/auth/login',[AuthController::class,'login']);
 Route::middleware('auth:sanctum')->group(function(){
  Route::post('/auth/logout',[AuthController::class,'logout']);
  Route::get('/attendance/today',[AttendanceController::class,'today']); Route::get('/attendance/history',[AttendanceController::class,'history']);
  Route::get('/gps/current',[GpsController::class,'current']); Route::get('/gps/history',[GpsController::class,'history']);
  Route::get('/settings/attendance-tracking',[SettingsController::class,'show']);
  Route::middleware('device.required')->group(function(){Route::post('/attendance/start',[AttendanceController::class,'start']);Route::post('/attendance/end',[AttendanceController::class,'end']);Route::post('/gps/locations',[GpsController::class,'ingest']);Route::post('/gps/privacy-acknowledgement',[GpsController::class,'acknowledge']);});
 });
});
