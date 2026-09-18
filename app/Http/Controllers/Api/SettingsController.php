<?php
namespace App\Http\Controllers\Api; use App\Http\Controllers\Controller; use App\Services\TrackingSettingsService; use App\Support\ApiResponse; use Illuminate\Http\Request;
class SettingsController extends Controller { public function show(Request $r,TrackingSettingsService $s){return ApiResponse::success($s->get($r->user()->tenant));} }
