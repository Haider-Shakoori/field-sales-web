<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CurrentLocation;
use App\Models\Customer;
use App\Models\CustomerVisit;
use App\Models\LocationHistory;
use App\Models\Salesman;
use App\Services\FieldScopeService;
use App\Services\GeoService;
use App\Services\TenantClock;
use App\Services\TrackingSettingsService;
use App\Support\ApiResponse;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class TrackingController extends Controller
{
    public function live(Request $request, FieldScopeService $scope, TrackingSettingsService $settingsService)
    {
        $user = $request->user()->load('tenant');
        $ids = $scope->salesmanIds($user);
        $settings = $settingsService->get($user->tenant);
        $staleBefore = now()->subMinutes($settings['gps_stale_after_minutes']);

        $locations = CurrentLocation::where('tenant_id', $user->tenant_id)
            ->whereIn('salesman_id', $ids)
            ->with(['salesman.user'])
            ->get();

        return ApiResponse::success($locations->map(function ($location) use ($staleBefore) {
            return [
                'salesman_id' => $location->salesman_id,
                'user_id' => $location->user_id,
                'name' => $location->salesman?->user?->name,
                'employee_code' => $location->salesman?->employee_code,
                'latitude' => (float) $location->latitude,
                'longitude' => (float) $location->longitude,
                'accuracy' => (float) $location->horizontal_accuracy,
                'speed' => $location->speed !== null ? (float) $location->speed : null,
                'battery_level' => $location->battery_level,
                'is_mock_location' => (bool) $location->is_mock_location,
                'recorded_at' => $location->recorded_at?->toISOString(),
                'received_at' => $location->received_at?->toISOString(),
                'status' => $location->recorded_at?->gte($staleBefore) ? 'online' : 'stale',
            ];
        })->values());
    }

    public function route(Request $request, string $salesmanUuid, FieldScopeService $scope, TenantClock $clock, GeoService $geo)
    {
        $validated = $request->validate(['date' => ['required', 'date_format:Y-m-d']]);
        $viewer = $request->user()->load('tenant');
        $salesman = Salesman::where('tenant_id', $viewer->tenant_id)->where('uuid', $salesmanUuid)->firstOrFail();

        if (! $scope->canSeeSalesman($viewer, $salesman->id, $validated['date'])) {
            return ApiResponse::error('Forbidden.', 403, null, 'FORBIDDEN');
        }

        [$from, $to] = $clock->dayBoundsUtc($viewer->tenant, $validated['date']);

        $points = LocationHistory::where('tenant_id', $viewer->tenant_id)
            ->where('salesman_id', $salesman->id)
            ->whereBetween('recorded_at', [$from, $to])
            ->orderBy('recorded_at')
            ->get(['latitude', 'longitude', 'horizontal_accuracy', 'speed', 'recorded_at']);

        $distance = 0.0;
        for ($i = 1; $i < $points->count(); $i++) {
            if ((float) $points[$i - 1]->horizontal_accuracy > 50 || (float) $points[$i]->horizontal_accuracy > 50) {
                continue;
            }
            $distance += $geo->distanceMeters(
                (float) $points[$i - 1]->latitude,
                (float) $points[$i - 1]->longitude,
                (float) $points[$i]->latitude,
                (float) $points[$i]->longitude
            );
        }

        $visits = CustomerVisit::where('tenant_id', $viewer->tenant_id)
            ->where('salesman_id', $salesman->id)
            ->whereBetween('checked_in_at', [$from, $to])
            ->with('customer:id,uuid,code,name,shop_name,latitude,longitude')
            ->orderBy('checked_in_at')
            ->get();

        $planned = Customer::where('tenant_id', $viewer->tenant_id)
            ->where('assigned_salesman_id', $salesman->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'uuid', 'code', 'name', 'shop_name', 'latitude', 'longitude', 'route_id']);

        return ApiResponse::success([
            'salesman' => [
                'uuid' => $salesman->uuid,
                'employee_code' => $salesman->employee_code,
                'name' => $salesman->user?->name,
            ],
            'date' => $validated['date'],
            'points' => $points,
            'distance_km' => round($distance / 1000, 3),
            'visits' => $visits,
            'planned_customers' => $planned,
            'coverage' => [
                'planned' => $planned->count(),
                'visited' => $visits->where('is_planned', true)->pluck('customer_id')->unique()->count(),
                'unplanned' => $visits->where('is_planned', false)->count(),
            ],
        ]);
    }

    public function gaps(Request $request, string $salesmanUuid, FieldScopeService $scope, TenantClock $clock)
    {
        $validated = $request->validate(['date' => ['required', 'date_format:Y-m-d']]);
        $viewer = $request->user()->load('tenant');
        $salesman = Salesman::where('tenant_id', $viewer->tenant_id)->where('uuid', $salesmanUuid)->firstOrFail();

        if (! $scope->canSeeSalesman($viewer, $salesman->id, $validated['date'])) {
            return ApiResponse::error('Forbidden.', 403, null, 'FORBIDDEN');
        }

        [$from, $to] = $clock->dayBoundsUtc($viewer->tenant, $validated['date']);
        $times = LocationHistory::where('tenant_id', $viewer->tenant_id)
            ->where('salesman_id', $salesman->id)
            ->whereBetween('recorded_at', [$from, $to])
            ->orderBy('recorded_at')
            ->pluck('recorded_at');

        $gaps = [];
        for ($i = 1; $i < $times->count(); $i++) {
            $previous = CarbonImmutable::parse($times[$i - 1]);
            $current = CarbonImmutable::parse($times[$i]);
            $seconds = $previous->diffInSeconds($current);
            if ($seconds > 120) {
                $gaps[] = [
                    'from' => $previous->toISOString(),
                    'to' => $current->toISOString(),
                    'seconds' => $seconds,
                ];
            }
        }

        return ApiResponse::success($gaps);
    }
}
