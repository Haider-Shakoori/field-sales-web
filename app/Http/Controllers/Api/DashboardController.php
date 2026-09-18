<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Collection;
use App\Models\CurrentLocation;
use App\Models\CustomerVisit;
use App\Models\SalesOrder;
use App\Models\Salesman;
use App\Models\WorkSession;
use App\Services\FieldScopeService;
use App\Services\TenantClock;
use App\Services\TrackingSettingsService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function summary(Request $request, FieldScopeService $scope, TenantClock $clock, TrackingSettingsService $settingsService)
    {
        $user = $request->user()->load('tenant');
        $salesmanIds = $scope->salesmanIds($user);
        $localDate = $clock->now($user->tenant)->toDateString();
        [$from, $to] = $clock->dayBoundsUtc($user->tenant, $localDate);
        $settings = $settingsService->get($user->tenant);
        $staleBefore = now()->subMinutes($settings['gps_stale_after_minutes']);

        $activeSalesmen = Salesman::where('tenant_id', $user->tenant_id)
            ->whereIn('id', $salesmanIds)
            ->where('is_active', true)
            ->count();

        $started = WorkSession::where('tenant_id', $user->tenant_id)
            ->whereIn('salesman_id', $salesmanIds)
            ->whereDate('date', $localDate)
            ->count();

        $working = WorkSession::where('tenant_id', $user->tenant_id)
            ->whereIn('salesman_id', $salesmanIds)
            ->whereDate('date', $localDate)
            ->where('status', 'active')
            ->count();

        $trackingOnline = CurrentLocation::where('tenant_id', $user->tenant_id)
            ->whereIn('salesman_id', $salesmanIds)
            ->where('recorded_at', '>=', $staleBefore)
            ->count();

        $visits = CustomerVisit::where('tenant_id', $user->tenant_id)
            ->whereIn('salesman_id', $salesmanIds)
            ->whereBetween('checked_in_at', [$from, $to])
            ->count();

        $ordersQuery = SalesOrder::where('tenant_id', $user->tenant_id)
            ->whereIn('salesman_id', $salesmanIds)
            ->whereBetween('ordered_at', [$from, $to])
            ->whereNotIn('status', ['cancelled']);

        $collections = Collection::where('tenant_id', $user->tenant_id)
            ->whereIn('salesman_id', $salesmanIds)
            ->whereBetween('collected_at', [$from, $to])
            ->sum('amount');

        return ApiResponse::success([
            'date' => $localDate,
            'timezone' => $settings['timezone'],
            'salesmen' => [
                'active' => $activeSalesmen,
                'started_day' => $started,
                'working_now' => $working,
                'not_started' => max(0, $activeSalesmen - $started),
                'tracking_online' => $trackingOnline,
                'tracking_stale_or_offline' => max(0, $working - $trackingOnline),
            ],
            'visits' => $visits,
            'orders' => (clone $ordersQuery)->count(),
            'sales_value' => (float) (clone $ordersQuery)->sum('total_amount'),
            'collections_value' => (float) $collections,
        ]);
    }
}
