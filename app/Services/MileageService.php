<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\LocationHistory;
use App\Models\Tenant;
use App\Models\WorkSession;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class MileageService
{
    public function __construct(
        private readonly TenantClock $clock,
    ) {}

    public function calculateGpsDistance(WorkSession $session): float
    {
        $end = $session->end_time ?: now();

        $points = LocationHistory::query()
            ->where('user_id', $session->user_id)
            ->whereBetween('recorded_at', [$session->start_time, $end])
            ->where('horizontal_accuracy', '<=', 50)
            ->where('is_mock_location', false)
            ->orderBy('recorded_at')
            ->get([
                'latitude',
                'longitude',
                'recorded_at',
            ]);

        if ($points->count() < 2) {
            return 0.0;
        }

        $distance = 0.0;

        for ($index = 1; $index < $points->count(); $index++) {
            $previous = $points[$index - 1];
            $current = $points[$index];

            $kilometres = $this->distance(
                (float) $previous->latitude,
                (float) $previous->longitude,
                (float) $current->latitude,
                (float) $current->longitude,
            );

            $seconds = max(
                1,
                $previous->recorded_at->diffInSeconds($current->recorded_at),
            );
            $speedKmh = $kilometres / ($seconds / 3600);

            if ($speedKmh > 200) {
                continue;
            }

            $distance += $kilometres;
        }

        return round($distance, 3);
    }

    public function refreshCompletedSession(WorkSession $session): WorkSession
    {
        if ($session->status !== 'completed') {
            return $session;
        }

        $session->forceFill([
            'gps_distance_km' => $this->calculateGpsDistance($session),
            'distance_calculated_at' => now(),
        ])->save();

        return $session->fresh();
    }

    public function rows(
        Tenant $tenant,
        string $dateFrom,
        string $dateTo,
        ?int $salesmanId = null,
    ): Collection {
        $timezone = $this->clock->timezone($tenant);
        $localStart = CarbonImmutable::parse(
            $dateFrom.' 00:00:00',
            $timezone,
        );
        $localEnd = CarbonImmutable::parse(
            $dateTo.' 23:59:59',
            $timezone,
        );
        $utcStart = $localStart->utc();
        $utcEnd = $localEnd->utc();

        $sessions = WorkSession::query()
            ->with('salesman.user.branch')
            ->whereBetween('date', [$dateFrom, $dateTo])
            ->when(
                $salesmanId,
                fn ($query) => $query->where('salesman_id', $salesmanId),
            )
            ->orderByDesc('date')
            ->orderBy('salesman_id')
            ->get();

        $fuel = Expense::query()
            ->where('category', 'fuel')
            ->where('status', 'approved')
            ->whereBetween('spent_at', [$utcStart, $utcEnd])
            ->when(
                $salesmanId,
                fn ($query) => $query->where('salesman_id', $salesmanId),
            )
            ->get()
            ->groupBy(fn (Expense $expense) => $expense->salesman_id.'|'
                .$expense->spent_at->setTimezone($timezone)->toDateString());

        return $sessions->map(function (WorkSession $session) use (
            $fuel,
        ): array {
            $gpsDistance = $session->status === 'completed'
                ? ($session->gps_distance_km !== null
                    ? (float) $session->gps_distance_km
                    : $this->calculateGpsDistance($session))
                : $this->calculateGpsDistance($session);

            $odometerDistance = $this->odometerDistance($session);
            $effectiveDistance = $odometerDistance ?? $gpsDistance;
            $fuelRows = $fuel->get(
                $session->salesman_id.'|'.$session->date->toDateString(),
                collect(),
            );
            $fuelLitres = round(
                $fuelRows->sum(fn (Expense $expense) => (float) ($expense->fuel_liters ?? 0)),
                3,
            );
            $fuelCostByCurrency = $fuelRows
                ->groupBy('currency')
                ->map(
                    fn (Collection $rows) => round(
                        $rows->sum(fn (Expense $expense) => (float) $expense->amount),
                        4,
                    )
                )
                ->all();

            return [
                'session' => $session,
                'gps_distance_km' => round($gpsDistance, 3),
                'odometer_distance_km' => $odometerDistance,
                'effective_distance_km' => round($effectiveDistance, 3),
                'distance_variance_km' => $odometerDistance === null
                    ? null
                    : round($odometerDistance - $gpsDistance, 3),
                'fuel_liters' => $fuelLitres,
                'fuel_cost_by_currency' => $fuelCostByCurrency,
                'km_per_liter' => $fuelLitres > 0
                    ? round($effectiveDistance / $fuelLitres, 2)
                    : null,
                'cost_per_km' => $effectiveDistance > 0
                    ? collect($fuelCostByCurrency)->map(
                        fn ($amount) => round((float) $amount / $effectiveDistance, 4)
                    )->all()
                    : [],
            ];
        });
    }

    public function odometerDistance(WorkSession $session): ?float
    {
        if (
            $session->odometer_start_km === null
            || $session->odometer_end_km === null
        ) {
            return null;
        }

        $distance = (float) $session->odometer_end_km
            - (float) $session->odometer_start_km;

        return $distance >= 0 ? round($distance, 2) : null;
    }

    private function distance(
        float $lat1,
        float $lon1,
        float $lat2,
        float $lon2,
    ): float {
        $earthRadius = 6371;
        $deltaLatitude = deg2rad($lat2 - $lat1);
        $deltaLongitude = deg2rad($lon2 - $lon1);

        $a = sin($deltaLatitude / 2) ** 2
            + cos(deg2rad($lat1))
            * cos(deg2rad($lat2))
            * sin($deltaLongitude / 2) ** 2;

        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
