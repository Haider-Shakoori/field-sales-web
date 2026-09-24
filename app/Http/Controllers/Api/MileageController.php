<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MileageService;
use App\Services\TenantClock;
use App\Support\ApiResponse;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MileageController extends Controller
{
    public function today(
        Request $request,
        MileageService $mileage,
        TenantClock $clock,
    ): JsonResponse {
        $user = $request->user()->loadMissing(['tenant', 'salesman']);
        abort_unless($user->salesman, 403);

        $date = $clock->now($user->tenant)->toDateString();
        $row = $mileage->rows(
            $user->tenant,
            $date,
            $date,
            $user->salesman->id,
        )->first();

        return ApiResponse::success(
            $row ? $this->payload($row) : null,
        );
    }

    public function history(
        Request $request,
        MileageService $mileage,
        TenantClock $clock,
    ): JsonResponse {
        $validated = $request->validate([
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => [
                'nullable',
                'date_format:Y-m-d',
                'after_or_equal:date_from',
            ],
        ]);

        $user = $request->user()->loadMissing(['tenant', 'salesman']);
        abort_unless($user->salesman, 403);

        $timezone = $clock->timezone($user->tenant);
        $dateTo = $validated['date_to']
            ?? CarbonImmutable::now($timezone)->toDateString();
        $dateFrom = $validated['date_from']
            ?? CarbonImmutable::parse($dateTo, $timezone)
                ->subDays(29)
                ->toDateString();

        $rows = $mileage->rows(
            $user->tenant,
            $dateFrom,
            $dateTo,
            $user->salesman->id,
        );

        return ApiResponse::success(
            $rows->map(fn (array $row) => $this->payload($row))->values()->all(),
        );
    }

    private function payload(array $row): array
    {
        $session = $row['session'];

        return [
            'session_uuid' => $session->uuid,
            'date' => $session->date?->toDateString(),
            'status' => $session->status,
            'vehicle_reference' => $session->vehicle_reference,
            'odometer_start_km' => $session->odometer_start_km === null
                ? null
                : (float) $session->odometer_start_km,
            'odometer_end_km' => $session->odometer_end_km === null
                ? null
                : (float) $session->odometer_end_km,
            'gps_distance_km' => $row['gps_distance_km'],
            'odometer_distance_km' => $row['odometer_distance_km'],
            'effective_distance_km' => $row['effective_distance_km'],
            'distance_variance_km' => $row['distance_variance_km'],
            'fuel_liters' => $row['fuel_liters'],
            'fuel_cost_by_currency' => $row['fuel_cost_by_currency'],
            'km_per_liter' => $row['km_per_liter'],
            'cost_per_km' => $row['cost_per_km'],
        ];
    }
}
