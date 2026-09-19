<?php

namespace App\Services;

use App\Models\Collection;
use App\Models\CurrentLocation;
use App\Models\CustomerVisit;
use App\Models\Expense;
use App\Models\Order;
use App\Models\Salesman;
use App\Models\SalesmanAssignment;
use App\Models\User;
use App\Models\WorkSession;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection as SupportCollection;

class DashboardService
{
    public function __construct(private readonly TenantClock $clock) {}

    public function summary(User $actor): array
    {
        $actor->loadMissing(['tenant', 'supervisor']);
        [$start, $end, $localDate] = $this->window($actor);
        $salesmanIds = $this->visibleSalesmanIds($actor, $localDate);
        $locations = $this->liveLocations($actor, $salesmanIds);

        return [
            'local_date' => $localDate,
            'timezone' => $this->clock->timezone($actor->tenant),
            'salesmen' => [
                'total' => $salesmanIds->count(),
                'live' => collect($locations)->where('freshness', 'live')->count(),
                'stale' => collect($locations)->where('freshness', 'stale')->count(),
                'offline' => collect($locations)->where('freshness', 'offline')->count(),
                'on_duty' => collect($locations)->where('on_duty', true)->count(),
            ],
            'visits' => [
                'completed' => CustomerVisit::query()
                    ->whereIn('salesman_id', $salesmanIds)
                    ->where('status', 'completed')
                    ->whereBetween('checked_in_at', [$start, $end])
                    ->count(),
                'active' => CustomerVisit::query()
                    ->whereIn('salesman_id', $salesmanIds)
                    ->where('status', 'active')
                    ->count(),
            ],
            'orders' => [
                'approved_count' => Order::query()
                    ->whereIn('salesman_id', $salesmanIds)
                    ->where('status', 'approved')
                    ->whereBetween('ordered_at', [$start, $end])
                    ->count(),
                'pending_count' => Order::query()
                    ->whereIn('salesman_id', $salesmanIds)
                    ->where('status', 'pending')
                    ->count(),
                'totals' => $this->moneyTotals(
                    Order::query()
                        ->whereIn('salesman_id', $salesmanIds)
                        ->where('status', 'approved')
                        ->whereBetween('ordered_at', [$start, $end]),
                    'grand_total',
                ),
            ],
            'collections' => [
                'verified_count' => Collection::query()
                    ->whereIn('salesman_id', $salesmanIds)
                    ->where('status', 'verified')
                    ->whereBetween('collected_at', [$start, $end])
                    ->count(),
                'pending_count' => Collection::query()
                    ->whereIn('salesman_id', $salesmanIds)
                    ->where('status', 'pending')
                    ->count(),
                'totals' => $this->moneyTotals(
                    Collection::query()
                        ->whereIn('salesman_id', $salesmanIds)
                        ->where('status', 'verified')
                        ->whereBetween('collected_at', [$start, $end]),
                    'amount',
                ),
            ],
            'expenses' => [
                'approved_count' => Expense::query()
                    ->whereIn('salesman_id', $salesmanIds)
                    ->where('status', 'approved')
                    ->whereBetween('spent_at', [$start, $end])
                    ->count(),
                'pending_count' => Expense::query()
                    ->whereIn('salesman_id', $salesmanIds)
                    ->where('status', 'pending')
                    ->count(),
                'totals' => $this->moneyTotals(
                    Expense::query()
                        ->whereIn('salesman_id', $salesmanIds)
                        ->where('status', 'approved')
                        ->whereBetween('spent_at', [$start, $end]),
                    'amount',
                ),
            ],
        ];
    }

    public function recentActivity(User $actor, int $limit = 12): array
    {
        $actor->loadMissing(['tenant', 'supervisor']);
        [, , $localDate] = $this->window($actor);
        $salesmanIds = $this->visibleSalesmanIds($actor, $localDate);

        $orders = Order::with(['salesman.user', 'customer'])
            ->whereIn('salesman_id', $salesmanIds)
            ->latest('ordered_at')
            ->limit(6)
            ->get()
            ->map(fn (Order $order) => [
                'type' => 'order',
                'title' => $order->order_number,
                'subtitle' => ($order->salesman?->full_name ?? 'Salesman')
                    .' · '.($order->customer?->name ?? 'Customer')
                    .' · '.number_format((float) $order->grand_total, 2).' '.$order->currency,
                'status' => $order->status,
                'at' => $order->ordered_at,
            ]);

        $collections = Collection::with(['salesman.user', 'customer'])
            ->whereIn('salesman_id', $salesmanIds)
            ->latest('collected_at')
            ->limit(6)
            ->get()
            ->map(fn (Collection $collection) => [
                'type' => 'collection',
                'title' => $collection->receipt_number,
                'subtitle' => ($collection->salesman?->full_name ?? 'Salesman')
                    .' · '.($collection->customer?->name ?? 'Customer')
                    .' · '.number_format((float) $collection->amount, 2).' '.$collection->currency,
                'status' => $collection->status,
                'at' => $collection->collected_at,
            ]);

        $expenses = Expense::with(['salesman.user'])
            ->whereIn('salesman_id', $salesmanIds)
            ->latest('spent_at')
            ->limit(6)
            ->get()
            ->map(fn (Expense $expense) => [
                'type' => 'expense',
                'title' => $expense->expense_number,
                'subtitle' => ($expense->salesman?->full_name ?? 'Salesman')
                    .' · '.str($expense->category)->replace('_', ' ')->title()
                    .' · '.number_format((float) $expense->amount, 2).' '.$expense->currency,
                'status' => $expense->status,
                'at' => $expense->spent_at,
            ]);

        $visits = CustomerVisit::with(['salesman.user', 'customer'])
            ->whereIn('salesman_id', $salesmanIds)
            ->latest('checked_in_at')
            ->limit(6)
            ->get()
            ->map(fn (CustomerVisit $visit) => [
                'type' => 'visit',
                'title' => $visit->customer?->name ?? 'Customer visit',
                'subtitle' => ($visit->salesman?->full_name ?? 'Salesman')
                    .' · '.str($visit->status)->title(),
                'status' => $visit->status,
                'at' => $visit->checked_in_at,
            ]);

        return $orders
            ->concat($collections)
            ->concat($expenses)
            ->concat($visits)
            ->sortByDesc(fn (array $item) => $item['at']?->getTimestamp() ?? 0)
            ->take($limit)
            ->values()
            ->map(function (array $item): array {
                $item['at'] = $item['at']?->toISOString();

                return $item;
            })
            ->all();
    }

    public function liveLocations(User $actor, ?SupportCollection $salesmanIds = null): array
    {
        $actor->loadMissing(['tenant', 'supervisor']);
        [, , $localDate] = $this->window($actor);
        $salesmanIds ??= $this->visibleSalesmanIds($actor, $localDate);
        $now = now();

        $salesmen = Salesman::with(['user'])
            ->whereIn('id', $salesmanIds)
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();

        $currentBySalesman = CurrentLocation::query()
            ->whereIn('salesman_id', $salesmanIds)
            ->get()
            ->keyBy('salesman_id');

        $onDutyIds = WorkSession::query()
            ->whereIn('salesman_id', $salesmanIds)
            ->where('status', 'active')
            ->pluck('salesman_id')
            ->flip();

        return $salesmen->map(function (Salesman $salesman) use (
            $currentBySalesman,
            $onDutyIds,
            $now,
        ): array {
            $location = $currentBySalesman->get($salesman->id);

            if (! $location) {
                return [
                    'salesman_id' => $salesman->uuid,
                    'salesman_name' => $salesman->full_name,
                    'freshness' => 'offline',
                    'age_seconds' => null,
                    'on_duty' => $onDutyIds->has($salesman->id),
                    'location' => null,
                ];
            }

            $recordedAt = $location->recorded_at;
            $ageSeconds = $recordedAt
                ? max(0, $recordedAt->diffInSeconds($now))
                : null;
            $freshness = match (true) {
                $ageSeconds !== null && $ageSeconds <= 300 => 'live',
                $ageSeconds !== null && $ageSeconds <= 1800 => 'stale',
                default => 'offline',
            };

            return [
                'salesman_id' => $salesman->uuid,
                'salesman_name' => $salesman->full_name,
                'freshness' => $freshness,
                'age_seconds' => $ageSeconds,
                'on_duty' => $onDutyIds->has($salesman->id),
                'location' => [
                    'latitude' => (float) $location->latitude,
                    'longitude' => (float) $location->longitude,
                    'accuracy' => (float) $location->horizontal_accuracy,
                    'speed' => $location->speed === null ? null : (float) $location->speed,
                    'heading' => $location->heading === null ? null : (float) $location->heading,
                    'battery_level' => $location->battery_level === null
                        ? null
                        : (int) $location->battery_level,
                    'is_charging' => (bool) $location->is_charging,
                    'network_status' => $location->network_status,
                    'is_mock_location' => (bool) $location->is_mock_location,
                    'provider' => $location->provider,
                    'recorded_at' => $location->recorded_at?->toISOString(),
                    'received_at' => $location->received_at?->toISOString(),
                ],
            ];
        })->values()->all();
    }

    private function visibleSalesmanIds(User $actor, string $localDate): SupportCollection
    {
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

        return $query->pluck('id');
    }

    private function window(User $actor): array
    {
        $localNow = $this->clock->now($actor->tenant);
        $localDate = $localNow->toDateString();
        $timezone = $this->clock->timezone($actor->tenant);
        $start = CarbonImmutable::parse($localDate.' 00:00:00', $timezone)->utc();
        $end = $start->addDay();

        return [$start, $end, $localDate];
    }

    private function moneyTotals($query, string $column): array
    {
        return $query
            ->selectRaw('currency, SUM('.$column.') AS total')
            ->groupBy('currency')
            ->orderBy('currency')
            ->get()
            ->map(fn ($row) => [
                'currency' => $row->currency,
                'total' => round((float) $row->total, 4),
            ])
            ->values()
            ->all();
    }
}
