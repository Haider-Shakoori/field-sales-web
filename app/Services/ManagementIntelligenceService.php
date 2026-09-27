<?php

namespace App\Services;

use App\Models\Collection as CustomerCollection;
use App\Models\CustomerVisit;
use App\Models\Order;
use App\Models\RouteCustomer;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection as SupportCollection;

final class ManagementIntelligenceService
{
    public function __construct(
        private readonly SupervisorScorecardService $scorecards,
        private readonly RouteExecutionAnalyticsService $routeExecution,
        private readonly TrackingSettingsService $tracking,
        private readonly FieldIntelligenceSettingsService $settings,
        private readonly TenantClock $clock,
    ) {}

    public function build(
        User $actor,
        string $date,
        ?string $supervisorUuid = null,
    ): array {
        $actor->loadMissing('tenant');
        $timezone = $this->clock->timezone($actor->tenant);
        $localDate = CarbonImmutable::createFromFormat(
            'Y-m-d',
            $date,
            $timezone,
        )->startOfDay();

        $scorecard = $this->scorecards->build(
            $actor,
            $date,
            $date,
            $supervisorUuid,
        );
        $featureSettings = $this->settings->settingsFor(
            $actor->tenant,
        );
        $tracking = $this->tracking->get($actor->tenant);
        $routeByCode = $this->routeExecutionSummary(
            collect($scorecard['rows']),
            $localDate,
            $timezone,
            $tracking,
            $featureSettings,
        )->keyBy('employee_code');
        $salesmanIds = collect($scorecard['rows'])
            ->pluck('salesman.id')
            ->filter()
            ->values();

        $liveBacklog = $localDate->isSameDay(
            CarbonImmutable::now($timezone),
        );
        $pendingOrders = $liveBacklog
            ? $this->pendingOrders($salesmanIds)
            : collect();
        $pendingCollections = $liveBacklog
            ? $this->pendingCollections($salesmanIds)
            : collect();
        $expectedProgress = $this->expectedRouteProgress(
            $localDate,
            $timezone,
            $tracking,
        );
        $routeTolerance = (int) $featureSettings[
            'management_route_progress_tolerance_percent'
        ];
        $targetThreshold = (int) $featureSettings[
            'management_target_attention_percent'
        ];
        $workdayStarted = $this->workdayStarted(
            $localDate,
            $timezone,
            $tracking,
        );

        $rows = collect($scorecard['rows'])
            ->map(function (array $scoreRow) use (
                $routeByCode,
                $pendingOrders,
                $pendingCollections,
                $expectedProgress,
                $routeTolerance,
                $targetThreshold,
                $workdayStarted,
                $liveBacklog,
            ): array {
                $salesman = $scoreRow['salesman'];
                $route = $routeByCode->get(
                    $salesman->employee_code,
                    $this->emptyRoute($salesman->employee_code),
                );
                $started = (int) $scoreRow['attendance']['days'] > 0;
                $routeGap = round(
                    $expectedProgress
                    - (float) $route['completion_percent'],
                    1,
                );
                $routeBehind = $route['optimization_enabled']
                    && (int) $route['assigned_stops'] > 0
                    && $expectedProgress > 0
                    && $routeGap > $routeTolerance;
                $targetPercent = $scoreRow[
                    'target_average_percent'
                ];
                $reasons = [];
                $score = 0;

                if ($workdayStarted && ! $started) {
                    $reasons[] = 'attendance_not_started';
                    $score += 5;
                }

                if ((int) $scoreRow['attendance']['late_starts'] > 0) {
                    $reasons[] = 'late_start';
                    $score += 2;
                }

                if ((int) $route['missed_stops'] > 0) {
                    $reasons[] = 'missed_planned_visits';
                    $score += 5;
                }

                if ($routeBehind) {
                    $reasons[] = 'route_behind_expected_progress';
                    $score += 4;
                }

                if ((int) $route['overflow_stops'] > 0) {
                    $reasons[] = 'route_capacity_risk';
                    $score += 4;
                }

                if ((int) $route['off_route_visits'] > 0) {
                    $reasons[] = 'off_route_visits';
                    $score += 2;
                }

                if ((int) $route['urgent_remaining'] > 0) {
                    $reasons[] = 'urgent_route_stops_remaining';
                    $score += 3;
                }

                $pendingOrderCount = $liveBacklog
                    ? (int) ($pendingOrders->get($salesman->id) ?? 0)
                    : null;
                $pendingCollectionCount = $liveBacklog
                    ? (int) (
                        $pendingCollections->get($salesman->id) ?? 0
                    )
                    : null;

                if (($pendingOrderCount ?? 0) > 0) {
                    $reasons[] = 'pending_order_approvals';
                    $score += 1;
                }

                if (($pendingCollectionCount ?? 0) > 0) {
                    $reasons[] = 'pending_collection_verification';
                    $score += 2;
                }

                if ((int) $scoreRow['follow_ups']['overdue'] > 0) {
                    $reasons[] = 'overdue_follow_ups';
                    $score += 2;
                }

                if ((int) $scoreRow['unresolved_flags'] > 0) {
                    $reasons[] = 'unresolved_visit_flags';
                    $score += 3;
                }

                if (
                    $targetPercent !== null
                    && (float) $targetPercent < $targetThreshold
                ) {
                    $reasons[] = 'target_below_attention_threshold';
                    $score += 2;
                }

                return [
                    'salesman' => $salesman,
                    'assignment' => $scoreRow['assignment'],
                    'attendance' => $scoreRow['attendance'],
                    'visits' => $scoreRow['visits'],
                    'orders' => $scoreRow['orders'],
                    'collections' => $scoreRow['collections'],
                    'follow_ups' => $scoreRow['follow_ups'],
                    'unresolved_flags' => $scoreRow[
                        'unresolved_flags'
                    ],
                    'targets' => $scoreRow['targets'],
                    'target_average_percent' => $targetPercent,
                    'pending_orders' => $pendingOrderCount,
                    'pending_collections' => $pendingCollectionCount,
                    'route' => $route,
                    'expected_route_progress_percent' => $expectedProgress,
                    'route_progress_gap_percent' => $routeGap,
                    'route_behind' => $routeBehind,
                    'attention_reasons' => $reasons,
                    'attention_score' => $score,
                    'attention_level' => match (true) {
                        $score >= 8 => 'high',
                        $score >= 3 => 'watch',
                        default => 'clear',
                    },
                ];
            })
            ->sort(function (array $left, array $right): int {
                $scoreOrder = $right['attention_score']
                    <=> $left['attention_score'];

                if ($scoreOrder !== 0) {
                    return $scoreOrder;
                }

                return $left['salesman']->employee_code
                    <=> $right['salesman']->employee_code;
            })
            ->values();

        $summary = [
            'salesmen' => $rows->count(),
            'started' => $rows->filter(
                fn (array $row) => (
                    (int) $row['attendance']['days'] > 0
                )
            )->count(),
            'not_started' => $workdayStarted
                ? $rows->filter(
                    fn (array $row) => (
                        (int) $row['attendance']['days'] === 0
                    )
                )->count()
                : 0,
            'late_starts' => $rows->sum(
                fn (array $row) => (
                    (int) $row['attendance']['late_starts']
                )
            ),
            'assigned_stops' => $rows->sum(
                fn (array $row) => (
                    (int) $row['route']['assigned_stops']
                )
            ),
            'visited_planned_stops' => $rows->sum(
                fn (array $row) => (
                    (int) $row['route']['visited_planned_stops']
                )
            ),
            'remaining_stops' => $rows->sum(
                fn (array $row) => (
                    (int) $row['route']['remaining_stops']
                )
            ),
            'missed_stops' => $rows->sum(
                fn (array $row) => (
                    (int) $row['route']['missed_stops']
                )
            ),
            'off_route_visits' => $rows->sum(
                fn (array $row) => (
                    (int) $row['route']['off_route_visits']
                )
            ),
            'behind_routes' => $rows->where(
                'route_behind',
                true,
            )->count(),
            'capacity_risk_routes' => $rows->filter(
                fn (array $row) => (
                    (int) $row['route']['overflow_stops'] > 0
                )
            )->count(),
            'average_route_completion_percent' => $rows->isEmpty()
                ? 0.0
                : round(
                    (float) $rows->avg(
                        'route.completion_percent',
                    ),
                    1,
                ),
            'expected_route_progress_percent' => $expectedProgress,
            'pending_orders' => $liveBacklog
                ? $rows->sum(
                    fn (array $row) => (
                        (int) ($row['pending_orders'] ?? 0)
                    )
                )
                : null,
            'pending_collections' => $liveBacklog
                ? $rows->sum(
                    fn (array $row) => (
                        (int) ($row['pending_collections'] ?? 0)
                    )
                )
                : null,
            'overdue_follow_ups' => $rows->sum(
                fn (array $row) => (
                    (int) $row['follow_ups']['overdue']
                )
            ),
            'unresolved_flags' => $rows->sum(
                fn (array $row) => (
                    (int) $row['unresolved_flags']
                )
            ),
            'below_target' => $rows->filter(
                fn (array $row) => (
                    $row['target_average_percent'] !== null
                    && (float) $row['target_average_percent']
                        < $targetThreshold
                )
            )->count(),
            'attention_salesmen' => $rows->whereIn(
                'attention_level',
                ['high', 'watch'],
            )->count(),
            'high_attention_salesmen' => $rows->where(
                'attention_level',
                'high',
            )->count(),
        ];

        return [
            'date' => $date,
            'timezone' => $timezone,
            'generated_at' => CarbonImmutable::now(
                $timezone,
            )->toIso8601String(),
            'supervisor' => $scorecard['supervisor'],
            'thresholds' => [
                'route_progress_tolerance_percent' => $routeTolerance,
                'target_attention_percent' => $targetThreshold,
            ],
            'workday' => [
                'start' => $tracking['workday_start_time'],
                'end' => $tracking['workday_end_time'],
                'started' => $workdayStarted,
                'expected_route_progress_percent' => $expectedProgress,
            ],
            'live_backlog' => $liveBacklog,
            'summary' => $summary,
            'briefing' => [
                'attention_salesmen' => $summary[
                    'attention_salesmen'
                ],
                'high_attention_salesmen' => $summary[
                    'high_attention_salesmen'
                ],
                'started' => $summary['started'],
                'not_started' => $summary['not_started'],
                'late_starts' => $summary['late_starts'],
                'behind_routes' => $summary['behind_routes'],
                'missed_stops' => $summary['missed_stops'],
                'off_route_visits' => $summary[
                    'off_route_visits'
                ],
                'pending_orders' => $summary['pending_orders'],
                'pending_collections' => $summary[
                    'pending_collections'
                ],
                'overdue_follow_ups' => $summary[
                    'overdue_follow_ups'
                ],
                'below_target' => $summary['below_target'],
                'focus' => $rows
                    ->whereIn(
                        'attention_level',
                        ['high', 'watch'],
                    )
                    ->take(8)
                    ->map(fn (array $row) => [
                        'salesman' => $row['salesman']->full_name,
                        'employee_code' => $row[
                            'salesman'
                        ]->employee_code,
                        'attention_level' => $row[
                            'attention_level'
                        ],
                        'attention_score' => $row[
                            'attention_score'
                        ],
                        'reasons' => $row[
                            'attention_reasons'
                        ],
                    ])
                    ->values()
                    ->all(),
            ],
            'rows' => $rows,
        ];
    }

    public function supervisorOptions(User $actor)
    {
        return $this->scorecards->supervisorOptions($actor);
    }

    /**
     * Management intelligence needs route execution across an entire team.
     * Rebuilding the full smart-route planner once per salesman is too expensive
     * for larger tenants, so this path batches assignments, customers and visits.
     */
    private function routeExecutionSummary(
        SupportCollection $scoreRows,
        CarbonImmutable $localDate,
        string $timezone,
        array $tracking,
        array $featureSettings,
    ): SupportCollection {
        if ($scoreRows->isEmpty()) {
            return collect();
        }

        $salesmanIds = $scoreRows
            ->pluck('salesman.id')
            ->filter()
            ->unique()
            ->values();

        $assignments = $scoreRows
            ->mapWithKeys(fn (array $row) => [
                $row['salesman']->id => $row['assignment'],
            ])
            ->filter();

        $routeIds = $assignments
            ->pluck('route_id')
            ->filter()
            ->unique()
            ->values();
        $routeCustomers = $routeIds->isEmpty()
            ? collect()
            : RouteCustomer::query()
                ->with('customer:id,uuid,name,is_active')
                ->whereIn('route_id', $routeIds)
                ->orderBy('sequence_number')
                ->get()
                ->filter(fn (RouteCustomer $row) => $row->customer?->is_active)
                ->groupBy('route_id');

        $start = $localDate->utc();
        $end = $localDate->addDay()->utc();
        $visits = CustomerVisit::query()
            ->with('customer:id,uuid,name')
            ->whereIn('salesman_id', $salesmanIds)
            ->where('status', 'completed')
            ->where('checked_in_at', '>=', $start)
            ->where('checked_in_at', '<', $end)
            ->get(['id', 'salesman_id', 'customer_id'])
            ->groupBy('salesman_id');

        $smartRoutesEnabled = (bool) (
            $featureSettings['smart_routes_enabled'] ?? true
        );
        $capacityEnabled = (bool) (
            $featureSettings['route_enforce_workday_capacity'] ?? true
        );
        $bufferMinutes = max(
            0,
            (int) ($featureSettings['route_time_buffer_minutes'] ?? 30),
        );
        [$workdayStart, $workdayEnd] = $this->workdayWindow(
            $localDate,
            $timezone,
            $tracking,
        );
        $capacityEnd = $workdayEnd->subMinutes($bufferMinutes);
        if ($capacityEnd->lt($workdayStart)) {
            $capacityEnd = $workdayStart;
        }
        $capacityMinutes = max(
            0,
            (int) $workdayStart->diffInMinutes($capacityEnd),
        );
        $finalized = $this->routeDayFinalized(
            $localDate,
            $timezone,
            (string) ($tracking['workday_end_time'] ?? '17:00'),
        );

        return $scoreRows->map(function (array $scoreRow) use (
            $assignments,
            $routeCustomers,
            $visits,
            $smartRoutesEnabled,
            $capacityEnabled,
            $capacityMinutes,
            $finalized,
        ): array {
            $salesman = $scoreRow['salesman'];
            $assignment = $assignments->get($salesman->id);
            $planned = collect();
            $source = null;
            $sourceType = null;

            if ($smartRoutesEnabled && $assignment?->route_id) {
                $planned = collect($routeCustomers->get($assignment->route_id, collect()))
                    ->map(fn (RouteCustomer $membership) => [
                        'uuid' => $membership->customer?->uuid,
                        'name' => $membership->customer?->name,
                        'minutes' => max(1, (int) $membership->planned_visit_minutes),
                    ])
                    ->filter(fn (array $row) => filled($row['uuid']))
                    ->values();
                $source = $assignment->route?->name;
                $sourceType = 'route';
            } elseif ($smartRoutesEnabled && $assignment?->territory_id) {
                // A territory is a coverage scope, not an explicit daily stop list.
                $source = $assignment->territory?->name;
                $sourceType = 'territory';
            } elseif ($smartRoutesEnabled && $assignment?->branch_id) {
                // A branch is a coverage scope, not an explicit daily stop list.
                $source = $assignment->branch?->name;
                $sourceType = 'branch';
            }

            $plannedByUuid = $planned->keyBy('uuid');
            $salesmanVisits = collect($visits->get($salesman->id, collect()));
            $completedCustomerUuids = $salesmanVisits
                ->map(fn (CustomerVisit $visit) => $visit->customer?->uuid)
                ->filter()
                ->unique()
                ->values();
            $visitedPlanned = $completedCustomerUuids
                ->intersect($plannedByUuid->keys())
                ->values();
            $remainingStops = $planned
                ->reject(fn (array $stop) => $visitedPlanned->contains($stop['uuid']))
                ->values();
            $offRoute = $salesmanVisits
                ->filter(fn (CustomerVisit $visit) => $visit->customer?->uuid
                    && ! $plannedByUuid->has($visit->customer->uuid))
                ->values();

            $assigned = $planned->count();
            $visited = $visitedPlanned->count();
            $remaining = $remainingStops->count();
            $missed = $finalized ? $remaining : 0;
            $completion = $assigned > 0
                ? round(($visited / $assigned) * 100, 1)
                : 0.0;

            $usedMinutes = 0;
            $overflowStops = 0;
            foreach ($remainingStops as $stop) {
                $usedMinutes += max(1, (int) ($stop['minutes'] ?? 10));
                if (
                    $capacityEnabled
                    && $capacityMinutes > 0
                    && $usedMinutes > $capacityMinutes
                ) {
                    $overflowStops++;
                }
            }

            $capacityUtilization = $capacityMinutes > 0
                ? round(($usedMinutes / $capacityMinutes) * 100, 1)
                : ($usedMinutes > 0 ? 100.0 : 0.0);

            $status = match (true) {
                ! $smartRoutesEnabled => 'optimization_disabled',
                $missed > 0 => 'missed_stops',
                $overflowStops > 0 => 'capacity_risk',
                $assigned > 0 && $remaining === 0 => 'complete',
                $visited > 0 => 'in_progress',
                default => 'not_started',
            };

            return [
                'salesman' => $salesman->full_name,
                'employee_code' => $salesman->employee_code,
                'optimization_enabled' => $smartRoutesEnabled,
                'source' => $source,
                'source_type' => $sourceType,
                'assigned_stops' => $assigned,
                'visited_planned_stops' => $visited,
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
                'urgent_remaining' => 0,
                'high_remaining' => 0,
                'overflow_stops' => $overflowStops,
                'capacity_utilization_percent' => $capacityUtilization,
                'route_fits_workday' => $overflowStops === 0,
                'estimated_finish_at' => null,
                'execution_status' => $status,
            ];
        })->values();
    }

    private function routeDayFinalized(
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

    private function pendingOrders($salesmanIds)
    {
        if ($salesmanIds->isEmpty()) {
            return collect();
        }

        return Order::query()
            ->whereIn('salesman_id', $salesmanIds)
            ->where('status', 'pending')
            ->selectRaw('salesman_id, COUNT(*) AS total')
            ->groupBy('salesman_id')
            ->pluck('total', 'salesman_id');
    }

    private function pendingCollections($salesmanIds)
    {
        if ($salesmanIds->isEmpty()) {
            return collect();
        }

        return CustomerCollection::query()
            ->whereIn('salesman_id', $salesmanIds)
            ->where('status', 'pending')
            ->selectRaw('salesman_id, COUNT(*) AS total')
            ->groupBy('salesman_id')
            ->pluck('total', 'salesman_id');
    }

    private function expectedRouteProgress(
        CarbonImmutable $localDate,
        string $timezone,
        array $tracking,
    ): float {
        $now = CarbonImmutable::now($timezone);
        $today = $now->startOfDay();

        if ($localDate->lt($today)) {
            return 100.0;
        }

        if ($localDate->gt($today)) {
            return 0.0;
        }

        [$start, $end] = $this->workdayWindow(
            $localDate,
            $timezone,
            $tracking,
        );

        if ($now->lte($start)) {
            return 0.0;
        }

        if ($now->gte($end)) {
            return 100.0;
        }

        $totalSeconds = max(
            1,
            $start->diffInSeconds($end),
        );
        $elapsedSeconds = $start->diffInSeconds($now);

        return round(
            min(
                100,
                max(
                    0,
                    ($elapsedSeconds / $totalSeconds) * 100,
                ),
            ),
            1,
        );
    }

    private function workdayStarted(
        CarbonImmutable $localDate,
        string $timezone,
        array $tracking,
    ): bool {
        $now = CarbonImmutable::now($timezone);
        $today = $now->startOfDay();

        if ($localDate->lt($today)) {
            return true;
        }

        if ($localDate->gt($today)) {
            return false;
        }

        [$start] = $this->workdayWindow(
            $localDate,
            $timezone,
            $tracking,
        );

        return $now->gte($start);
    }

    private function workdayWindow(
        CarbonImmutable $localDate,
        string $timezone,
        array $tracking,
    ): array {
        $start = CarbonImmutable::parse(
            $localDate->toDateString().' '
                .($tracking['workday_start_time'] ?? '08:00'),
            $timezone,
        );
        $end = CarbonImmutable::parse(
            $localDate->toDateString().' '
                .($tracking['workday_end_time'] ?? '17:00'),
            $timezone,
        );

        if ($end->lte($start)) {
            $end = $end->addDay();
        }

        return [$start, $end];
    }

    private function emptyRoute(string $employeeCode): array
    {
        return [
            'salesman' => null,
            'employee_code' => $employeeCode,
            'optimization_enabled' => true,
            'source' => null,
            'source_type' => null,
            'assigned_stops' => 0,
            'visited_planned_stops' => 0,
            'remaining_stops' => 0,
            'missed_stops' => 0,
            'off_route_visits' => 0,
            'off_route_customers' => [],
            'completion_percent' => 0.0,
            'urgent_remaining' => 0,
            'high_remaining' => 0,
            'overflow_stops' => 0,
            'capacity_utilization_percent' => 0.0,
            'route_fits_workday' => true,
            'estimated_finish_at' => null,
            'execution_status' => 'not_started',
        ];
    }
}
