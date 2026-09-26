<?php

namespace App\Services;

use App\Models\Collection;
use App\Models\CurrentLocation;
use App\Models\CustomerVisit;
use App\Models\Expense;
use App\Models\Order;
use App\Models\Salesman;
use App\Models\SalesmanAssignment;
use App\Models\SupervisorAssignment;
use App\Models\User;
use App\Models\WorkSession;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    public function __construct(private readonly TenantClock $clock) {}

    public function summary(User $actor, ?array $locations = null): array
    {
        $actor->loadMissing(['tenant', 'supervisor']);
        [$start, $end, $localDate] = $this->window($actor);
        $salesmanIds = $this->visibleSalesmanIds($actor, $localDate);
        $locations ??= $this->liveLocations($actor, $salesmanIds);

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

    public function analytics(User $actor, int $days = 7): array
    {
        $actor->loadMissing(['tenant', 'supervisor']);
        $days = max(2, min(31, $days));
        $timezone = $this->clock->timezone($actor->tenant);
        $localToday = $this->clock->now($actor->tenant)->startOfDay();
        $localStart = $localToday->subDays($days - 1);
        $start = $localStart->utc();
        $end = $localToday->addDay()->utc();
        $salesmanIds = $this->visibleSalesmanIds($actor, $localToday->toDateString());

        $dates = collect(range(0, $days - 1))
            ->map(fn (int $offset) => $localStart->addDays($offset)->toDateString());
        $dateIndex = $dates->flip();

        $salesByCurrency = [];
        $orders = Order::query()
            ->whereIn('salesman_id', $salesmanIds)
            ->where('status', 'approved')
            ->whereBetween('ordered_at', [$start, $end])
            ->get(['ordered_at', 'currency', 'grand_total']);

        foreach ($orders as $order) {
            $date = CarbonImmutable::parse($order->ordered_at)
                ->setTimezone($timezone)
                ->toDateString();
            $index = $dateIndex->get($date);

            if ($index === null) {
                continue;
            }

            $currency = strtoupper((string) $order->currency);
            $salesByCurrency[$currency] ??= array_fill(0, $days, 0.0);
            $salesByCurrency[$currency][$index] += (float) $order->grand_total;
        }

        ksort($salesByCurrency);

        $visitStarted = array_fill(0, $days, 0);
        $visitCompleted = array_fill(0, $days, 0);
        $visits = CustomerVisit::query()
            ->whereIn('salesman_id', $salesmanIds)
            ->whereBetween('checked_in_at', [$start, $end])
            ->get(['checked_in_at', 'status']);

        foreach ($visits as $visit) {
            $date = CarbonImmutable::parse($visit->checked_in_at)
                ->setTimezone($timezone)
                ->toDateString();
            $index = $dateIndex->get($date);

            if ($index === null) {
                continue;
            }

            $visitStarted[$index]++;

            if ($visit->status === 'completed') {
                $visitCompleted[$index]++;
            }
        }

        return [
            'days' => $days,
            'labels' => $dates
                ->map(fn (string $date) => CarbonImmutable::parse($date, $timezone)->format('M j'))
                ->all(),
            'sales' => [
                'datasets' => collect($salesByCurrency)
                    ->map(fn (array $values, string $currency) => [
                        'currency' => $currency,
                        'values' => array_map(
                            fn (float $value) => round($value, 4),
                            $values,
                        ),
                    ])
                    ->values()
                    ->all(),
            ],
            'visits' => [
                'started' => $visitStarted,
                'completed' => $visitCompleted,
            ],
        ];
    }

    public function liveLocations(
        User $actor,
        ?SupportCollection $salesmanIds = null,
        bool $withTracks = false,
    ): array {
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

        $tracks = $withTracks
            ? $this->dailyTracks($actor, $salesmanIds, $localDate)
            : [];

        return $salesmen->map(function (Salesman $salesman) use (
            $currentBySalesman,
            $onDutyIds,
            $now,
            $tracks,
        ): array {
            $location = $currentBySalesman->get($salesman->id);

            $payload = [
                'salesman_id' => $salesman->uuid,
                'employee_code' => $salesman->employee_code,
                'salesman_name' => $salesman->full_name,
                'freshness' => 'offline',
                'status' => 'offline',
                'age_seconds' => null,
                'on_duty' => $onDutyIds->has($salesman->id),
                'location' => null,
            ];

            if ($location) {
                $recordedAt = $location->recorded_at;
                $ageSeconds = $recordedAt
                    ? max(0, $recordedAt->diffInSeconds($now))
                    : null;
                $freshness = match (true) {
                    $ageSeconds !== null && $ageSeconds <= 300 => 'live',
                    $ageSeconds !== null && $ageSeconds <= 1800 => 'stale',
                    default => 'offline',
                };

                $payload['freshness'] = $freshness;
                $payload['status'] = match ($freshness) {
                    'live' => 'online',
                    'stale' => 'idle',
                    default => 'offline',
                };
                $payload['age_seconds'] = $ageSeconds;
                $payload['location'] = [
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
                ];
            }

            if (array_key_exists($salesman->id, $tracks)) {
                $payload['track'] = $tracks[$salesman->id];
            }

            return $payload;
        })->values()->all();
    }

    private function dailyTracks(
        User $actor,
        SupportCollection $salesmanIds,
        string $localDate,
    ): array {
        if ($salesmanIds->isEmpty()) {
            return [];
        }

        [$start, $end] = $this->window($actor);
        $bucketSeconds = max(60, (int) config('tenancy.tracking.map_track_bucket_seconds', 300));
        $bucketExpression = DB::connection()->getDriverName() === 'sqlite'
            ? "CAST(strftime('%s', recorded_at) AS INTEGER) / {$bucketSeconds}"
            : "FLOOR(UNIX_TIMESTAMP(recorded_at) / {$bucketSeconds})";

        $sampled = DB::query()
            ->fromSub(
                DB::table('location_history')
                    ->selectRaw('salesman_id, latitude, longitude, horizontal_accuracy, recorded_at')
                    ->selectRaw("ROW_NUMBER() OVER (PARTITION BY salesman_id, {$bucketExpression} ORDER BY recorded_at) AS bucket_rank")
                    ->where('tenant_id', $actor->tenant_id)
                    ->whereIn('salesman_id', $salesmanIds)
                    ->where('recorded_at', '>=', $start)
                    ->where('recorded_at', '<', $end),
                'track_points',
            )
            ->where('bucket_rank', 1)
            ->orderBy('salesman_id')
            ->orderBy('recorded_at')
            ->get()
            ->groupBy('salesman_id');

        if ($sampled->isEmpty()) {
            return [];
        }

        $sessions = WorkSession::query()
            ->where('tenant_id', $actor->tenant_id)
            ->whereIn('salesman_id', $salesmanIds)
            ->where(function ($query) use ($localDate): void {
                $query->whereDate('date', $localDate)
                    ->orWhere('status', 'active');
            })
            ->orderBy('start_time')
            ->get()
            ->keyBy('salesman_id');

        $tracks = [];

        foreach ($sampled as $salesmanId => $rows) {
            $session = $sessions->get($salesmanId);
            $points = [];
            $distanceKm = 0.0;
            $previous = null;

            if ($session
                && $session->start_latitude !== null
                && $session->start_longitude !== null
                && $session->start_time !== null
                && $session->start_time->lte(CarbonImmutable::parse($rows->first()->recorded_at))) {
                $points[] = [
                    round((float) $session->start_latitude, 5),
                    round((float) $session->start_longitude, 5),
                    $session->start_time->toISOString(),
                ];
                $previous = [round((float) $session->start_latitude, 5), round((float) $session->start_longitude, 5), 0.0];
            }

            foreach ($rows as $row) {
                $point = [
                    round((float) $row->latitude, 5),
                    round((float) $row->longitude, 5),
                    CarbonImmutable::parse($row->recorded_at)->toISOString(),
                ];

                $accuracy = (float) $row->horizontal_accuracy;

                if ($previous !== null && $previous[2] <= 50 && $accuracy <= 50) {
                    $distanceKm += $this->distanceKm(
                        $previous[0],
                        $previous[1],
                        $point[0],
                        $point[1],
                    );
                }

                $points[] = $point;
                $previous = [$point[0], $point[1], $accuracy];
            }

            if (count($points) < 2) {
                continue;
            }

            $tracks[$salesmanId] = [
                'points' => $points,
                'distance_km' => round($distanceKm, 2),
            ];
        }

        return $tracks;
    }

    private function distanceKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371;
        $deltaLatitude = deg2rad($lat2 - $lat1);
        $deltaLongitude = deg2rad($lon2 - $lon1);

        $a = sin($deltaLatitude / 2) ** 2
            + cos(deg2rad($lat1))
            * cos(deg2rad($lat2))
            * sin($deltaLongitude / 2) ** 2;

        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    public function dashboardVariant(User $actor): array
    {
        $role = (string) $actor->role;

        return match ($role) {
            'supervisor' => [
                'key' => 'supervisor',
                'title' => 'Team operations dashboard',
                'subtitle' => 'Current salesman assignments and field activity.',
            ],
            'accountant' => [
                'key' => 'accountant',
                'title' => 'Finance operations dashboard',
                'subtitle' => 'Collections, expenses, and financial activity.',
            ],
            'auditor' => [
                'key' => 'auditor',
                'title' => 'Audit operations dashboard',
                'subtitle' => 'Read-only operational visibility across the tenant.',
            ],
            'sales_manager' => [
                'key' => 'sales_manager',
                'title' => 'Sales operations dashboard',
                'subtitle' => 'Team performance, approvals, and field execution.',
            ],
            default => [
                'key' => 'company',
                'title' => 'Company operations dashboard',
                'subtitle' => 'Today’s field-sales execution at a glance.',
            ],
        };
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
        } elseif ($actor->hasAnyRole(['sales_manager'])) {
            $supervisorIds = SupervisorAssignment::query()
                ->where('sales_manager_id', $actor->id)
                ->current($localDate)
                ->pluck('supervisor_id');

            if ($supervisorIds->isEmpty()) {
                return collect();
            }

            $assigned = SalesmanAssignment::query()
                ->whereIn('supervisor_id', $supervisorIds)
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
