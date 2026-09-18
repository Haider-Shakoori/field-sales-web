<?php
use App\Http\Controllers\Web\{AuthController,TrackingSettingsController}; use Illuminate\Support\Facades\Route;
Route::redirect('/','/admin/tracking-settings'); Route::get('/login',[AuthController::class,'create'])->name('login'); Route::post('/login',[AuthController::class,'store']);
Route::middleware('auth')->group(function(){Route::post('/logout',[AuthController::class,'destroy'])->name('logout');Route::get('/admin/tracking-settings',[TrackingSettingsController::class,'edit'])->name('tracking.edit');Route::put('/admin/tracking-settings',[TrackingSettingsController::class,'update'])->name('tracking.update');});
