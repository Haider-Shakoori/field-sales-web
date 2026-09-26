<?php

namespace App\Services;

use App\Models\CustomerVisit;
use App\Models\Salesman;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

final class RouteExecutionAnalyticsService
{
    public function __construct(
        private readonly DailyRoutePlannerService $planner,
        private readonly ReportService $reports,
        private readonly TrackingSettingsService $tracking,
        private readonly TenantClock $clock,
    ) {}

    public function build(
        User $actor,
        string $date,
        ?string $salesmanQuery = null,
    ): array {
        $actor->loadMissing('tenant');
        $timezone = $this->clock->timezone($actor->tenant);
        $localDate = CarbonImmutable::createFromFormat(
            'Y-m-d',
            $date,
            $timezone,
        )->startOfDay();

        $salesmen = $this->reports->options(
            $actor,
            $localDate->toDateString(),
        )['salesmen'];

        if (filled($salesmanQuery)) {
            $needle = mb_strtolower(trim((string) $salesmanQuery));
            $salesmen = $salesmen
                ->filter(function (Salesman $salesman) use ($needle): bool {
                    return str_contains(
                        mb_strtolower($salesman->employee_code.' '.$salesman->full_name),
                        $needle,
                    );
                })
                ->values();
        }

        $tracking = $this->tracking->get($actor->tenant);
        $rows = $salesmen
            ->take(50)
            ->map(fn (Salesman $salesman) => $this->row(
                $salesman,
                $localDate,
                $timezone,
                $tracking,
            ))
            ->values();

        return [
            'date' => $localDate->toDateString(),
            'timezone' => $timezone,
            'salesman_filter' => $salesmanQuery,
            'summary' => [
                'salesmen' => $rows->count(),
                'assigned_stops' => $rows->sum('assigned_stops'),
                'visited_planned_stops' => $rows->sum('visited_planned_stops'),
                'remaining_stops' => $rows->sum('remaining_stops'),
                'missed_stops' => $rows->sum('missed_stops'),
                'off_route_visits' => $rows->sum('off_route_visits'),
                'overflow_stops' => $rows->sum('overflow_stops'),
                'average_completion_percent' => $rows->isEmpty()
                    ? 0.0
                    : round((float) $rows->avg('completion_percent'), 1),
            ],
            'salesmen' => $rows->all(),
        ];
    }

    private function row(
        Salesman $salesman,
        CarbonImmutable $localDate,
        string $timezone,
        array $tracking,
    ): array {
        $plan = $this->planner->planFor($salesman, $localDate);
        $stops = collect($plan['stops'] ?? []);
        $plannedCustomerUuids = $stops
            ->pluck('customer_id')
            ->filter()
            ->values();

        $start = $localDate->utc();
        $end = $localDate->addDay()->utc();

        $visits = CustomerVisit::query()
            ->with('customer:id,uuid,name')
            ->where('salesman_id', $salesman->id)
            ->where('status', 'completed')
            ->where('checked_in_at', '>=', $start)
            ->where('checked_in_at', '<', $end)
            ->get();

        $offRoute = $visits
            ->filter(fn (CustomerVisit $visit) => $visit->customer?->uuid
                && ! $plannedCustomerUuids->contains($visit->customer->uuid))
            ->values();

        $assigned = $stops->count();
        $visitedPlanned = $stops->where('visited_today', true)->count();
        $remaining = $stops->where('visited_today', false)->count();
        $finalized = $this->isFinalizedDay(
            $localDate,
            $timezone,
            (string) ($tracking['workday_end_time'] ?? '17:00'),
        );
        $missed = $finalized ? $remaining : 0;
        $completion = $assigned > 0
            ? round(($visitedPlanned / $assigned) * 100, 1)
            : 0.0;

        $remainingStops = $stops->where('visited_today', false);
        $lastDeparture = $remainingStops
            ->pluck('estimated_departure_at')
            ->filter()
            ->last();

        $status = match (true) {
            ($plan['enabled'] ?? true) === false => 'optimization_disabled',
            $missed > 0 => 'missed_stops',
            (int) data_get($plan, 'summary.overflow_stops', 0) > 0 => 'capacity_risk',
            $assigned > 0 && $remaining === 0 => 'complete',
            $visitedPlanned > 0 => 'in_progress',
            default => 'not_started',
        };

        return [
            'salesman' => $salesman->full_name,
            'employee_code' => $salesman->employee_code,
            'optimization_enabled' => (bool) ($plan['enabled'] ?? true),
            'source' => data_get($plan, 'source.name'),
            'source_type' => data_get($plan, 'source.type'),
            'assigned_stops' => $assigned,
            'visited_planned_stops' => $visitedPlanned,
            'remaining_stops' => $remaining,
            'missed_stops' => $missed,
            'off_route_visits' => $offRoute->count(),
            'off_route_customers' => $offRoute
                ->map(fn (CustomerVisit $visit) => $visit->customer?->name)
                ->filter()
                ->unique()
                ->values()
                ->all(),
            'completion_percent' => $completion,
            'urgent_remaining' => $remainingStops->where('priority', 'urgent')->count(),
            'high_remaining' => $remainingStops->where('priority', 'high')->count(),
            'overflow_stops' => (int) data_get($plan, 'summary.overflow_stops', 0),
            'capacity_utilization_percent' => (float) data_get(
                $plan,
                'summary.capacity_utilization_percent',
                0,
            ),
            'route_fits_workday' => (bool) data_get(
                $plan,
                'summary.route_fits_workday',
                true,
            ),
            'estimated_finish_at' => $lastDeparture,
            'execution_status' => $status,
        ];
    }

    private function isFinalizedDay(
        CarbonImmutable $localDate,
        string $timezone,
        string $workdayEnd,
    ): bool {
        $now = CarbonImmutable::now($timezone);

        if ($localDate->lt($now->startOfDay())) {
            return true;
        }

        if ($localDate->gt($now->startOfDay())) {
            return false;
        }

        $end = CarbonImmutable::parse(
            $localDate->toDateString().' '.$workdayEnd,
            $timezone,
        );

        return $now->gte($end);
    }
}
