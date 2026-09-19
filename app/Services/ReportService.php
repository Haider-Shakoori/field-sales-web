<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Collection;
use App\Models\CustomerVisit;
use App\Models\Expense;
use App\Models\LocationHistory;
use App\Models\Order;
use App\Models\Salesman;
use App\Models\SalesmanAssignment;
use App\Models\Territory;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class ReportService
{
    public const TYPES = ['sales', 'visits', 'gps', 'performance'];

    public function __construct(private readonly TenantClock $clock) {}

    public function build(User $actor, string $type, array $filters): array
    {
        abort_unless(in_array($type, self::TYPES, true), 404);

        [$start, $end, $fromDate, $toDate] = $this->window($actor, $filters);
        $salesmanIds = $this->visibleSalesmanIds($actor, $toDate, $filters);

        return match ($type) {
            'sales' => $this->sales($salesmanIds, $start, $end, $filters, $fromDate, $toDate),
            'visits' => $this->visits($salesmanIds, $start, $end, $filters, $fromDate, $toDate),
            'gps' => $this->gps($salesmanIds, $start, $end, $fromDate, $toDate),
            'performance' => $this->performance(
                $salesmanIds,
                $start,
                $end,
                $fromDate,
                $toDate,
            ),
        };
    }

    public function options(User $actor, ?string $date = null): array
    {
        $localDate = $date ?: $this->clock->now($actor->tenant)->toDateString();
        $ids = $this->visibleSalesmanIds($actor, $localDate, []);

        $salesmen = Salesman::query()
            ->whereIn('id', $ids)
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get(['id', 'uuid', 'employee_code', 'first_name', 'last_name']);

        $assignmentQuery = SalesmanAssignment::query()
            ->whereIn('salesman_id', $ids)
            ->current($localDate);

        $branchIds = (clone $assignmentQuery)
            ->whereNotNull('branch_id')
            ->pluck('branch_id')
            ->unique();
        $territoryIds = (clone $assignmentQuery)
            ->whereNotNull('territory_id')
            ->pluck('territory_id')
            ->unique();

        if (! $actor->hasAnyRole(['supervisor'])) {
            $branches = Branch::active()->orderBy('name')->get(['id', 'uuid', 'name']);
            $territories = Territory::active()->orderBy('name')->get(['id', 'uuid', 'name']);
        } else {
            $branches = Branch::active()
                ->whereIn('id', $branchIds)
                ->orderBy('name')
                ->get(['id', 'uuid', 'name']);
            $territories = Territory::active()
                ->whereIn('id', $territoryIds)
                ->orderBy('name')
                ->get(['id', 'uuid', 'name']);
        }

        return compact('salesmen', 'branches', 'territories');
    }

    private function sales(
        Collection $salesmanIds,
        CarbonImmutable $start,
        CarbonImmutable $end,
        array $filters,
        string $fromDate,
        string $toDate,
    ): array {
        $orders = Order::with(['salesman', 'customer'])
            ->whereIn('salesman_id', $salesmanIds)
            ->where('status', 'approved')
            ->where('ordered_at', '>=', $start)
            ->where('ordered_at', '<', $end)
            ->when(
                $filters['branch_id'] ?? null,
                fn ($query, $branchId) => $query->whereHas(
                    'customer',
                    fn ($customer) => $customer->where('branch_id', $branchId),
                ),
            )
            ->when(
                $filters['territory_id'] ?? null,
                fn ($query, $territoryId) => $query->whereHas(
                    'customer',
                    fn ($customer) => $customer->where('territory_id', $territoryId),
                ),
            )
            ->get();

        $rows = $orders
            ->groupBy(fn (Order $order) => $order->salesman_id.'|'.$order->currency)
            ->map(function (Collection $group): array {
                /** @var Order $first */
                $first = $group->first();

                return [
                    'salesman' => $first->salesman?->full_name ?? 'Unknown',
                    'employee_code' => $first->salesman?->employee_code ?? '—',
                    'currency' => $first->currency,
                    'orders' => $group->count(),
                    'sales_total' => round($group->sum(fn (Order $order) => (float) $order->grand_total), 4),
                ];
            })
            ->sortBy('salesman')
            ->values()
            ->all();

        $totals = $orders
            ->groupBy('currency')
            ->map(fn (Collection $group, string $currency) => [
                'currency' => $currency,
                'total' => round($group->sum(fn (Order $order) => (float) $order->grand_total), 4),
            ])
            ->values()
            ->all();

        return [
            'type' => 'sales',
            'title' => 'Sales report',
            'period' => [$fromDate, $toDate],
            'columns' => [
                'salesman' => 'Salesman',
                'employee_code' => 'Employee code',
                'currency' => 'Currency',
                'orders' => 'Approved orders',
                'sales_total' => 'Sales total',
            ],
            'rows' => $rows,
            'summary' => [
                'Approved orders' => $orders->count(),
                'Currency totals' => $this->moneyText($totals),
            ],
        ];
    }

    private function visits(
        Collection $salesmanIds,
        CarbonImmutable $start,
        CarbonImmutable $end,
        array $filters,
        string $fromDate,
        string $toDate,
    ): array {
        $visits = CustomerVisit::with(['salesman', 'customer'])
            ->withCount('suspiciousFlags')
            ->whereIn('salesman_id', $salesmanIds)
            ->where('checked_in_at', '>=', $start)
            ->where('checked_in_at', '<', $end)
            ->when(
                $filters['branch_id'] ?? null,
                fn ($query, $branchId) => $query->whereHas(
                    'customer',
                    fn ($customer) => $customer->where('branch_id', $branchId),
                ),
            )
            ->when(
                $filters['territory_id'] ?? null,
                fn ($query, $territoryId) => $query->whereHas(
                    'customer',
                    fn ($customer) => $customer->where('territory_id', $territoryId),
                ),
            )
            ->get();

        $rows = $visits
            ->groupBy('salesman_id')
            ->map(function (Collection $group): array {
                /** @var CustomerVisit $first */
                $first = $group->first();
                $completed = $group->where('status', 'completed')->count();

                return [
                    'salesman' => $first->salesman?->full_name ?? 'Unknown',
                    'employee_code' => $first->salesman?->employee_code ?? '—',
                    'visits' => $group->count(),
                    'completed' => $completed,
                    'completion_rate' => $group->count() > 0
                        ? round(($completed / $group->count()) * 100, 2).' %'
                        : '0 %',
                    'flagged' => $group->filter(
                        fn (CustomerVisit $visit) => (int) $visit->suspicious_flags_count > 0
                    )->count(),
                ];
            })
            ->sortBy('salesman')
            ->values()
            ->all();

        return [
            'type' => 'visits',
            'title' => 'Visit report',
            'period' => [$fromDate, $toDate],
            'columns' => [
                'salesman' => 'Salesman',
                'employee_code' => 'Employee code',
                'visits' => 'Visits',
                'completed' => 'Completed',
                'completion_rate' => 'Completion rate',
                'flagged' => 'Flagged visits',
            ],
            'rows' => $rows,
            'summary' => [
                'Visits' => $visits->count(),
                'Completed' => $visits->where('status', 'completed')->count(),
                'Flagged visits' => $visits->filter(
                    fn (CustomerVisit $visit) => (int) $visit->suspicious_flags_count > 0
                )->count(),
            ],
        ];
    }

    private function gps(
        Collection $salesmanIds,
        CarbonImmutable $start,
        CarbonImmutable $end,
        string $fromDate,
        string $toDate,
    ): array {
        $points = LocationHistory::query()
            ->whereIn('salesman_id', $salesmanIds)
            ->where('recorded_at', '>=', $start)
            ->where('recorded_at', '<', $end)
            ->get();

        $salesmen = Salesman::query()
            ->whereIn('id', $points->pluck('salesman_id')->unique())
            ->get()
            ->keyBy('id');

        $rows = $points
            ->groupBy('salesman_id')
            ->map(function (Collection $group, int|string $salesmanId) use ($salesmen): array {
                $salesman = $salesmen->get((int) $salesmanId);
                $mock = $group->where('is_mock_location', true)->count();
                $accuracy = $group->avg(fn (LocationHistory $point) => (float) $point->horizontal_accuracy);

                return [
                    'salesman' => $salesman?->full_name ?? 'Unknown',
                    'employee_code' => $salesman?->employee_code ?? '—',
                    'points' => $group->count(),
                    'mock_points' => $mock,
                    'average_accuracy_m' => round((float) $accuracy, 2),
                    'last_recorded_at' => $group->max('recorded_at')?->toISOString(),
                ];
            })
            ->sortBy('salesman')
            ->values()
            ->all();

        return [
            'type' => 'gps',
            'title' => 'GPS report',
            'period' => [$fromDate, $toDate],
            'columns' => [
                'salesman' => 'Salesman',
                'employee_code' => 'Employee code',
                'points' => 'GPS points',
                'mock_points' => 'Mock-location points',
                'average_accuracy_m' => 'Avg accuracy (m)',
                'last_recorded_at' => 'Last recorded at',
            ],
            'rows' => $rows,
            'summary' => [
                'GPS points' => $points->count(),
                'Mock-location points' => $points->where('is_mock_location', true)->count(),
            ],
        ];
    }

    private function performance(
        Collection $salesmanIds,
        CarbonImmutable $start,
        CarbonImmutable $end,
        string $fromDate,
        string $toDate,
    ): array {
        $salesmen = Salesman::query()
            ->whereIn('id', $salesmanIds)
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();

        $orders = Order::query()
            ->whereIn('salesman_id', $salesmanIds)
            ->where('status', 'approved')
            ->where('ordered_at', '>=', $start)
            ->where('ordered_at', '<', $end)
            ->get(['salesman_id', 'currency', 'grand_total']);

        $collections = Collection::query()
            ->whereIn('salesman_id', $salesmanIds)
            ->where('status', 'verified')
            ->where('collected_at', '>=', $start)
            ->where('collected_at', '<', $end)
            ->get(['salesman_id', 'currency', 'amount']);

        $expenses = Expense::query()
            ->whereIn('salesman_id', $salesmanIds)
            ->where('status', 'approved')
            ->where('spent_at', '>=', $start)
            ->where('spent_at', '<', $end)
            ->get(['salesman_id', 'currency', 'amount']);

        $visits = CustomerVisit::query()
            ->whereIn('salesman_id', $salesmanIds)
            ->where('checked_in_at', '>=', $start)
            ->where('checked_in_at', '<', $end)
            ->get(['salesman_id', 'status']);

        $rows = $salesmen->map(function (Salesman $salesman) use (
            $orders,
            $collections,
            $expenses,
            $visits,
        ): array {
            $salesmanOrders = $orders->where('salesman_id', $salesman->id);
            $salesmanCollections = $collections->where('salesman_id', $salesman->id);
            $salesmanExpenses = $expenses->where('salesman_id', $salesman->id);
            $salesmanVisits = $visits->where('salesman_id', $salesman->id);

            return [
                'salesman' => $salesman->full_name,
                'employee_code' => $salesman->employee_code,
                'sales' => $this->moneyRowsText($salesmanOrders, 'grand_total'),
                'collections' => $this->moneyRowsText($salesmanCollections, 'amount'),
                'expenses' => $this->moneyRowsText($salesmanExpenses, 'amount'),
                'visits' => $salesmanVisits->count(),
                'completed_visits' => $salesmanVisits->where('status', 'completed')->count(),
            ];
        })->all();

        return [
            'type' => 'performance',
            'title' => 'Performance report',
            'period' => [$fromDate, $toDate],
            'columns' => [
                'salesman' => 'Salesman',
                'employee_code' => 'Employee code',
                'sales' => 'Approved sales',
                'collections' => 'Verified collections',
                'expenses' => 'Approved expenses',
                'visits' => 'Visits',
                'completed_visits' => 'Completed visits',
            ],
            'rows' => $rows,
            'summary' => [
                'Salesmen' => $salesmen->count(),
                'Approved orders' => $orders->count(),
                'Verified collections' => $collections->count(),
                'Completed visits' => $visits->where('status', 'completed')->count(),
            ],
        ];
    }

    private function visibleSalesmanIds(
        User $actor,
        string $localDate,
        array $filters,
    ): Collection {
        $actor->loadMissing('supervisor');
        $query = Salesman::active();

        if ($actor->hasAnyRole(['supervisor'])) {
            if (! $actor->supervisor) {
                return collect();
            }

            $assigned = SalesmanAssignment::query()
                ->where('supervisor_id', $actor->supervisor->id)
                ->current($localDate)
                ->pluck('salesman_id');

            $query->whereIn('id', $assigned);
        }

        if ($filters['salesman_id'] ?? null) {
            $query->whereKey($filters['salesman_id']);
        }

        if (($filters['branch_id'] ?? null) || ($filters['territory_id'] ?? null)) {
            $query->whereHas('assignments', function ($assignment) use ($filters, $localDate): void {
                $assignment->current($localDate)
                    ->when(
                        $filters['branch_id'] ?? null,
                        fn ($q, $branchId) => $q->where('branch_id', $branchId),
                    )
                    ->when(
                        $filters['territory_id'] ?? null,
                        fn ($q, $territoryId) => $q->where('territory_id', $territoryId),
                    );
            });
        }

        return $query->pluck('id');
    }

    private function window(User $actor, array $filters): array
    {
        $timezone = $this->clock->timezone($actor->tenant);
        $today = $this->clock->now($actor->tenant)->toDateString();
        $fromDate = $filters['date_from'] ?? CarbonImmutable::parse($today, $timezone)
            ->subDays(29)
            ->toDateString();
        $toDate = $filters['date_to'] ?? $today;

        $start = CarbonImmutable::parse($fromDate.' 00:00:00', $timezone)->utc();
        $end = CarbonImmutable::parse($toDate.' 00:00:00', $timezone)->addDay()->utc();

        return [$start, $end, $fromDate, $toDate];
    }

    private function moneyRowsText(Collection $rows, string $column): string
    {
        return $this->moneyText(
            $rows->groupBy('currency')
                ->map(fn (Collection $group, string $currency) => [
                    'currency' => $currency,
                    'total' => round($group->sum(fn ($row) => (float) $row->{$column}), 4),
                ])
                ->values()
                ->all(),
        );
    }

    private function moneyText(array $totals): string
    {
        if ($totals === []) {
            return '—';
        }

        return collect($totals)
            ->map(fn (array $total) => number_format((float) $total['total'], 2).' '.$total['currency'])
            ->implode(' | ');
    }
}
