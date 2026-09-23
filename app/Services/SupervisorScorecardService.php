<?php

namespace App\Services;

use App\Models\Collection;
use App\Models\CustomerFollowUp;
use App\Models\CustomerVisit;
use App\Models\Order;
use App\Models\Salesman;
use App\Models\SalesmanAssignment;
use App\Models\SalesTarget;
use App\Models\Supervisor;
use App\Models\User;
use App\Models\VisitSuspiciousFlag;
use App\Models\WorkSession;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection as SupportCollection;

class SupervisorScorecardService
{
    public function __construct(
        private readonly TenantClock $clock,
        private readonly TargetProgressService $targets,
    ) {}

    public function build(
        User $actor,
        string $dateFrom,
        string $dateTo,
        ?string $supervisorUuid = null,
    ): array {
        $actor->loadMissing(['tenant', 'supervisor']);
        $timezone = $this->clock->timezone($actor->tenant);
        $rangeStart = CarbonImmutable::parse($dateFrom.' 00:00:00', $timezone)->utc();
        $rangeEnd = CarbonImmutable::parse($dateTo.' 00:00:00', $timezone)->addDay()->utc();
        $supervisor = $this->resolveSupervisor($actor, $supervisorUuid);
        $salesmen = $this->visibleSalesmen(
            $actor,
            $dateFrom,
            $dateTo,
            $supervisor,
        );

        if ($salesmen->isEmpty()) {
            return $this->emptyPayload(
                $timezone,
                $dateFrom,
                $dateTo,
                $supervisor,
            );
        }

        $salesmanIds = $salesmen->pluck('id');
        $assignments = $this->assignmentMap(
            $salesmanIds,
            $dateFrom,
            $dateTo,
        );
        $attendance = $this->attendance($salesmanIds, $dateFrom, $dateTo, $timezone);
        $visits = $this->visits($salesmanIds, $rangeStart, $rangeEnd);
        $orders = $this->orders($salesmanIds, $rangeStart, $rangeEnd);
        $collections = $this->collections($salesmanIds, $rangeStart, $rangeEnd);
        $followUps = $this->followUps($salesmanIds);
        $flags = $this->flags($salesmanIds, $rangeStart, $rangeEnd);
        $targetProgress = $this->targetProgress($salesmanIds, $dateTo);

        $rows = $salesmen
            ->sortBy('employee_code')
            ->values()
            ->map(function (Salesman $salesman) use (
                $assignments,
                $attendance,
                $visits,
                $orders,
                $collections,
                $followUps,
                $flags,
                $targetProgress,
            ): array {
                $attendanceRow = $attendance->get($salesman->id, []);
                $visitRow = $visits->get($salesman->id, []);
                $orderRow = $orders->get($salesman->id, [
                    'count' => 0,
                    'totals' => [],
                ]);
                $collectionRow = $collections->get($salesman->id, [
                    'count' => 0,
                    'totals' => [],
                ]);
                $targetRows = $targetProgress->get($salesman->id, collect());

                return [
                    'salesman' => $salesman,
                    'assignment' => $assignments->get($salesman->id),
                    'attendance' => [
                        'days' => (int) ($attendanceRow['days'] ?? 0),
                        'minutes' => (int) ($attendanceRow['minutes'] ?? 0),
                        'late_starts' => (int) ($attendanceRow['late_starts'] ?? 0),
                        'early_finishes' => (int) ($attendanceRow['early_finishes'] ?? 0),
                    ],
                    'visits' => [
                        'completed' => (int) ($visitRow['completed'] ?? 0),
                        'productive' => (int) ($visitRow['productive'] ?? 0),
                        'average_minutes' => (int) ($visitRow['average_minutes'] ?? 0),
                    ],
                    'orders' => $orderRow,
                    'collections' => $collectionRow,
                    'follow_ups' => [
                        'pending' => (int) ($followUps->get($salesman->id)['pending'] ?? 0),
                        'overdue' => (int) ($followUps->get($salesman->id)['overdue'] ?? 0),
                    ],
                    'unresolved_flags' => (int) ($flags->get($salesman->id) ?? 0),
                    'targets' => $targetRows->values()->all(),
                    'target_average_percent' => $targetRows->isEmpty()
                        ? null
                        : round((float) $targetRows->avg('progress_percent'), 2),
                ];
            });

        return [
            'period' => [
                'from' => $dateFrom,
                'to' => $dateTo,
                'timezone' => $timezone,
            ],
            'supervisor' => $supervisor,
            'summary' => [
                'salesmen' => $rows->count(),
                'attendance_days' => $rows->sum('attendance.days'),
                'worked_minutes' => $rows->sum('attendance.minutes'),
                'completed_visits' => $rows->sum('visits.completed'),
                'productive_visits' => $rows->sum('visits.productive'),
                'approved_orders' => $rows->sum('orders.count'),
                'verified_collections' => $rows->sum('collections.count'),
                'overdue_follow_ups' => $rows->sum('follow_ups.overdue'),
                'unresolved_flags' => $rows->sum('unresolved_flags'),
                'order_totals' => $this->combineMoneyTotals($rows, 'orders.totals'),
                'collection_totals' => $this->combineMoneyTotals($rows, 'collections.totals'),
            ],
            'rows' => $rows,
        ];
    }

    public function supervisorOptions(User $actor): SupportCollection
    {
        $actor->loadMissing('supervisor');

        if ($actor->hasAnyRole(['supervisor'])) {
            return $actor->supervisor
                ? collect([$actor->supervisor])
                : collect();
        }

        return Supervisor::active()
            ->with('user')
            ->orderBy('employee_code')
            ->get();
    }

    private function resolveSupervisor(
        User $actor,
        ?string $supervisorUuid,
    ): ?Supervisor {
        if ($actor->hasAnyRole(['supervisor'])) {
            return $actor->supervisor;
        }

        if (! $supervisorUuid) {
            return null;
        }

        return Supervisor::active()
            ->where('uuid', $supervisorUuid)
            ->firstOrFail();
    }

    private function visibleSalesmen(
        User $actor,
        string $dateFrom,
        string $dateTo,
        ?Supervisor $supervisor,
    ): SupportCollection {
        $query = Salesman::active()->with('user');

        if ($actor->hasAnyRole(['supervisor']) && ! $actor->supervisor) {
            return collect();
        }

        if ($supervisor) {
            $salesmanIds = SalesmanAssignment::query()
                ->where('supervisor_id', $supervisor->id)
                ->whereDate('effective_from', '<=', $dateTo)
                ->where(function ($window) use ($dateFrom): void {
                    $window->whereNull('effective_to')
                        ->orWhereDate('effective_to', '>=', $dateFrom);
                })
                ->pluck('salesman_id')
                ->unique();

            $query->whereIn('id', $salesmanIds);
        }

        return $query
            ->orderBy('employee_code')
            ->get();
    }

    private function assignmentMap(
        SupportCollection $salesmanIds,
        string $dateFrom,
        string $dateTo,
    ): SupportCollection {
        return SalesmanAssignment::with([
            'supervisor',
            'branch',
            'territory',
            'route',
        ])
            ->whereIn('salesman_id', $salesmanIds)
            ->whereDate('effective_from', '<=', $dateTo)
            ->where(function ($window) use ($dateFrom): void {
                $window->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>=', $dateFrom);
            })
            ->orderByDesc('effective_from')
            ->get()
            ->groupBy('salesman_id')
            ->map(fn (SupportCollection $rows) => $rows->first());
    }

    private function attendance(
        SupportCollection $salesmanIds,
        string $dateFrom,
        string $dateTo,
        string $timezone,
    ): SupportCollection {
        $rows = WorkSession::query()
            ->whereIn('salesman_id', $salesmanIds)
            ->whereBetween('date', [$dateFrom, $dateTo])
            ->selectRaw(
                'salesman_id,
                COUNT(*) AS days,
                COALESCE(SUM(duration_minutes), 0) AS minutes,
                SUM(CASE WHEN is_late_start = 1 THEN 1 ELSE 0 END) AS late_starts,
                SUM(CASE WHEN is_early_finish = 1 THEN 1 ELSE 0 END) AS early_finishes'
            )
            ->groupBy('salesman_id')
            ->get()
            ->mapWithKeys(fn ($row) => [
                $row->salesman_id => [
                    'days' => (int) $row->days,
                    'minutes' => (int) $row->minutes,
                    'late_starts' => (int) $row->late_starts,
                    'early_finishes' => (int) $row->early_finishes,
                ],
            ]);

        $today = CarbonImmutable::now($timezone)->toDateString();

        if ($dateFrom <= $today && $dateTo >= $today) {
            $now = now();

            WorkSession::query()
                ->whereIn('salesman_id', $salesmanIds)
                ->whereDate('date', $today)
                ->where('status', 'active')
                ->whereNull('duration_minutes')
                ->get()
                ->each(function (WorkSession $session) use (&$rows, $now): void {
                    $current = $rows->get($session->salesman_id, [
                        'days' => 0,
                        'minutes' => 0,
                        'late_starts' => 0,
                        'early_finishes' => 0,
                    ]);
                    $running = $session->start_time && $session->start_time->lte($now)
                        ? (int) floor($session->start_time->diffInMinutes($now))
                        : 0;
                    $current['minutes'] += $running;
                    $rows->put($session->salesman_id, $current);
                });
        }

        return $rows;
    }

    private function visits(
        SupportCollection $salesmanIds,
        CarbonImmutable $start,
        CarbonImmutable $end,
    ): SupportCollection {
        return CustomerVisit::query()
            ->whereIn('salesman_id', $salesmanIds)
            ->where('status', 'completed')
            ->where('checked_in_at', '>=', $start)
            ->where('checked_in_at', '<', $end)
            ->selectRaw(
                "salesman_id,
                COUNT(*) AS completed,
                SUM(CASE WHEN outcome IN ('order_placed','collection_made') THEN 1 ELSE 0 END) AS productive,
                COALESCE(AVG(duration_seconds), 0) AS average_seconds"
            )
            ->groupBy('salesman_id')
            ->get()
            ->mapWithKeys(fn ($row) => [
                $row->salesman_id => [
                    'completed' => (int) $row->completed,
                    'productive' => (int) $row->productive,
                    'average_minutes' => (int) round(((float) $row->average_seconds) / 60),
                ],
            ]);
    }

    private function orders(
        SupportCollection $salesmanIds,
        CarbonImmutable $start,
        CarbonImmutable $end,
    ): SupportCollection {
        $counts = Order::query()
            ->whereIn('salesman_id', $salesmanIds)
            ->where('status', 'approved')
            ->where('ordered_at', '>=', $start)
            ->where('ordered_at', '<', $end)
            ->selectRaw('salesman_id, COUNT(*) AS total')
            ->groupBy('salesman_id')
            ->pluck('total', 'salesman_id');

        $money = Order::query()
            ->whereIn('salesman_id', $salesmanIds)
            ->where('status', 'approved')
            ->where('ordered_at', '>=', $start)
            ->where('ordered_at', '<', $end)
            ->selectRaw('salesman_id, currency, SUM(grand_total) AS total')
            ->groupBy('salesman_id', 'currency')
            ->get()
            ->groupBy('salesman_id');

        return $salesmanIds->mapWithKeys(function ($salesmanId) use ($counts, $money): array {
            $totals = collect($money->get($salesmanId, collect()))
                ->map(fn ($row) => [
                    'currency' => strtoupper((string) $row->currency),
                    'total' => round((float) $row->total, 4),
                ])
                ->values()
                ->all();

            return [
                $salesmanId => [
                    'count' => (int) ($counts->get($salesmanId) ?? 0),
                    'totals' => $totals,
                ],
            ];
        });
    }

    private function collections(
        SupportCollection $salesmanIds,
        CarbonImmutable $start,
        CarbonImmutable $end,
    ): SupportCollection {
        $counts = Collection::query()
            ->whereIn('salesman_id', $salesmanIds)
            ->where('status', 'verified')
            ->where('collected_at', '>=', $start)
            ->where('collected_at', '<', $end)
            ->selectRaw('salesman_id, COUNT(*) AS total')
            ->groupBy('salesman_id')
            ->pluck('total', 'salesman_id');

        $money = Collection::query()
            ->whereIn('salesman_id', $salesmanIds)
            ->where('status', 'verified')
            ->where('collected_at', '>=', $start)
            ->where('collected_at', '<', $end)
            ->selectRaw('salesman_id, currency, SUM(amount) AS total')
            ->groupBy('salesman_id', 'currency')
            ->get()
            ->groupBy('salesman_id');

        return $salesmanIds->mapWithKeys(function ($salesmanId) use ($counts, $money): array {
            $totals = collect($money->get($salesmanId, collect()))
                ->map(fn ($row) => [
                    'currency' => strtoupper((string) $row->currency),
                    'total' => round((float) $row->total, 4),
                ])
                ->values()
                ->all();

            return [
                $salesmanId => [
                    'count' => (int) ($counts->get($salesmanId) ?? 0),
                    'totals' => $totals,
                ],
            ];
        });
    }

    private function followUps(SupportCollection $salesmanIds): SupportCollection
    {
        $now = now();

        return CustomerFollowUp::query()
            ->whereIn('assigned_salesman_id', $salesmanIds)
            ->where('status', 'pending')
            ->selectRaw(
                'assigned_salesman_id,
                COUNT(*) AS pending,
                SUM(CASE WHEN due_at < ? THEN 1 ELSE 0 END) AS overdue',
                [$now],
            )
            ->groupBy('assigned_salesman_id')
            ->get()
            ->mapWithKeys(fn ($row) => [
                $row->assigned_salesman_id => [
                    'pending' => (int) $row->pending,
                    'overdue' => (int) $row->overdue,
                ],
            ]);
    }

    private function flags(
        SupportCollection $salesmanIds,
        CarbonImmutable $start,
        CarbonImmutable $end,
    ): SupportCollection {
        return VisitSuspiciousFlag::query()
            ->join('customer_visits', 'customer_visits.id', '=', 'visit_suspicious_flags.visit_id')
            ->whereIn('customer_visits.salesman_id', $salesmanIds)
            ->whereNull('visit_suspicious_flags.reviewed_at')
            ->where('customer_visits.checked_in_at', '>=', $start)
            ->where('customer_visits.checked_in_at', '<', $end)
            ->selectRaw('customer_visits.salesman_id, COUNT(*) AS total')
            ->groupBy('customer_visits.salesman_id')
            ->pluck('total', 'customer_visits.salesman_id');
    }

    private function targetProgress(
        SupportCollection $salesmanIds,
        string $asOfDate,
    ): SupportCollection {
        $targets = SalesTarget::query()
            ->with(['salesman.user', 'tenant'])
            ->whereIn('salesman_id', $salesmanIds)
            ->whereDate('period_start', '<=', $asOfDate)
            ->whereDate('period_end', '>=', $asOfDate)
            ->orderByDesc('period_start')
            ->get()
            ->groupBy('salesman_id');

        return $salesmanIds->mapWithKeys(function ($salesmanId) use ($targets): array {
            $rows = collect($targets->get($salesmanId, collect()))
                ->unique('target_type')
                ->take(4)
                ->map(fn (SalesTarget $target) => $this->targets->payload($target));

            return [$salesmanId => $rows];
        });
    }

    private function combineMoneyTotals(
        SupportCollection $rows,
        string $path,
    ): array {
        return $rows
            ->flatMap(fn (array $row) => data_get($row, $path, []))
            ->groupBy('currency')
            ->map(fn (SupportCollection $items, string $currency) => [
                'currency' => $currency,
                'total' => round((float) $items->sum('total'), 4),
            ])
            ->sortKeys()
            ->values()
            ->all();
    }

    private function emptyPayload(
        string $timezone,
        string $dateFrom,
        string $dateTo,
        ?Supervisor $supervisor,
    ): array {
        return [
            'period' => [
                'from' => $dateFrom,
                'to' => $dateTo,
                'timezone' => $timezone,
            ],
            'supervisor' => $supervisor,
            'summary' => [
                'salesmen' => 0,
                'attendance_days' => 0,
                'worked_minutes' => 0,
                'completed_visits' => 0,
                'productive_visits' => 0,
                'approved_orders' => 0,
                'verified_collections' => 0,
                'overdue_follow_ups' => 0,
                'unresolved_flags' => 0,
                'order_totals' => [],
                'collection_totals' => [],
            ],
            'rows' => collect(),
        ];
    }
}
