<?php

namespace App\Services;

use App\Models\Collection as CustomerCollection;
use App\Models\Customer;
use App\Models\CustomerVisit;
use App\Models\Order;
use App\Models\Salesman;
use App\Models\SalesmanAssignment;
use App\Models\Territory;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class TerritoryHeatMapService
{
    public const METRICS = ['coverage', 'visits', 'sales', 'collections'];

    public function __construct(
        private readonly TenantClock $clock,
        private readonly FieldIntelligenceSettingsService $settings,
    ) {}

    public function build(User $actor, array $filters = []): array
    {
        $actor->loadMissing(['tenant', 'supervisor']);
        $timezone = $this->clock->timezone($actor->tenant);
        $today = $this->clock->now($actor->tenant)->toDateString();
        $toDate = (string) ($filters['date_to'] ?? $today);
        $fromDate = (string) ($filters['date_from'] ?? CarbonImmutable::parse($toDate, $timezone)->subDays(29)->toDateString());
        $start = CarbonImmutable::parse($fromDate.' 00:00:00', $timezone)->utc();
        $end = CarbonImmutable::parse($toDate.' 00:00:00', $timezone)->addDay()->utc();
        $metric = in_array($filters['metric'] ?? null, self::METRICS, true)
            ? (string) $filters['metric']
            : 'coverage';
        $featureSettings = $this->settings->settingsFor($actor->tenant);
        $underCoveredThreshold = (int) $featureSettings['territory_under_covered_threshold_percent'];

        if (! $featureSettings['territory_heat_map_enabled']) {
            $currency = strtoupper((string) ($filters['currency'] ?? 'AFN')) ?: 'AFN';

            return [
                'enabled' => false,
                'filters' => [
                    'date_from' => $fromDate,
                    'date_to' => $toDate,
                    'metric' => $metric,
                    'currency' => $currency,
                ],
                'timezone' => $timezone,
                'currencies' => [$currency],
                'territories' => [],
                'points' => [],
                'under_covered' => [],
                'under_covered_threshold_percent' => $underCoveredThreshold,
                'summary' => [
                    'territories' => 0,
                    'customers' => 0,
                    'mapped_customers' => 0,
                    'visited_customers' => 0,
                    'coverage_percent' => 0.0,
                    'visits' => 0,
                    'sales' => 0.0,
                    'collections' => 0.0,
                    'currency' => $currency,
                ],
            ];
        }

        $localDate = CarbonImmutable::parse($toDate, $timezone)->toDateString();
        $salesmanIds = $this->visibleSalesmanIds($actor, $localDate);
        $territories = $this->visibleTerritories($actor, $localDate, $salesmanIds);
        $territoryIds = $territories->pluck('id');

        $customers = Customer::active()
            ->whereIn('territory_id', $territoryIds)
            ->orderBy('name')
            ->get(['id', 'uuid', 'territory_id', 'code', 'name', 'latitude', 'longitude']);
        $customerIds = $customers->pluck('id');

        $orders = Order::query()
            ->whereIn('salesman_id', $salesmanIds)
            ->whereIn('customer_id', $customerIds)
            ->where('status', 'approved')
            ->where('ordered_at', '>=', $start)
            ->where('ordered_at', '<', $end)
            ->get(['customer_id', 'currency', 'grand_total', 'ordered_at']);
        $collections = CustomerCollection::query()
            ->whereIn('salesman_id', $salesmanIds)
            ->whereIn('customer_id', $customerIds)
            ->where('status', 'verified')
            ->where('collected_at', '>=', $start)
            ->where('collected_at', '<', $end)
            ->get(['customer_id', 'currency', 'amount', 'collected_at']);
        $visits = CustomerVisit::query()
            ->whereIn('salesman_id', $salesmanIds)
            ->whereIn('customer_id', $customerIds)
            ->where('status', 'completed')
            ->where('checked_in_at', '>=', $start)
            ->where('checked_in_at', '<', $end)
            ->get(['customer_id', 'checked_in_at']);

        $currencies = $orders->pluck('currency')
            ->merge($collections->pluck('currency'))
            ->filter()
            ->map(fn ($currency) => strtoupper((string) $currency))
            ->unique()
            ->sort()
            ->values();
        $requestedCurrency = strtoupper((string) ($filters['currency'] ?? ''));
        $currency = $requestedCurrency !== '' && $currencies->contains($requestedCurrency)
            ? $requestedCurrency
            : ($currencies->first() ?: 'AFN');

        $ordersByCustomer = $orders->groupBy('customer_id');
        $collectionsByCustomer = $collections->groupBy('customer_id');
        $visitsByCustomer = $visits->groupBy('customer_id');

        $customerRows = $customers->map(function (Customer $customer) use (
            $ordersByCustomer,
            $collectionsByCustomer,
            $visitsByCustomer,
            $currency,
        ): array {
            $customerOrders = $ordersByCustomer->get($customer->id, collect());
            $customerCollections = $collectionsByCustomer->get($customer->id, collect());
            $customerVisits = $visitsByCustomer->get($customer->id, collect());
            $sales = round($customerOrders
                ->where('currency', $currency)
                ->sum(fn (Order $order) => (float) $order->grand_total), 4);
            $collected = round($customerCollections
                ->where('currency', $currency)
                ->sum(fn (CustomerCollection $collection) => (float) $collection->amount), 4);

            return [
                'id' => $customer->uuid,
                'territory_id' => $customer->territory_id,
                'code' => $customer->code,
                'name' => $customer->name,
                'latitude' => $customer->latitude === null ? null : (float) $customer->latitude,
                'longitude' => $customer->longitude === null ? null : (float) $customer->longitude,
                'visits' => $customerVisits->count(),
                'visited' => $customerVisits->isNotEmpty(),
                'orders' => $customerOrders->where('currency', $currency)->count(),
                'sales' => $sales,
                'collections' => $collected,
            ];
        });

        $territoryRows = $territories->map(function (Territory $territory) use (
            $customerRows,
            $metric,
            $currency,
        ): array {
            $rows = $customerRows->where('territory_id', $territory->id);
            $customers = $rows->count();
            $visitedCustomers = $rows->where('visited', true)->count();
            $coverage = $customers > 0 ? round(($visitedCustomers / $customers) * 100, 2) : 0.0;
            $visits = $rows->sum('visits');
            $sales = round($rows->sum('sales'), 4);
            $collections = round($rows->sum('collections'), 4);
            $value = match ($metric) {
                'visits' => (float) $visits,
                'sales' => $sales,
                'collections' => $collections,
                default => $coverage,
            };

            return [
                'id' => $territory->uuid,
                'database_id' => $territory->id,
                'code' => $territory->code,
                'name' => $territory->name,
                'polygon' => $territory->polygon,
                'customers' => $customers,
                'mapped_customers' => $rows->whereNotNull('latitude')->whereNotNull('longitude')->count(),
                'visited_customers' => $visitedCustomers,
                'coverage_percent' => $coverage,
                'visits' => $visits,
                'sales' => $sales,
                'collections' => $collections,
                'currency' => $currency,
                'metric' => $metric,
                'metric_value' => $value,
            ];
        });

        $maxTerritoryValue = max(0.0, (float) $territoryRows->max('metric_value'));
        $territoryRows = $territoryRows->map(function (array $row) use ($metric, $maxTerritoryValue): array {
            $row['intensity'] = $metric === 'coverage'
                ? round(min(1, max(0, $row['coverage_percent'] / 100)), 4)
                : ($maxTerritoryValue > 0 ? round($row['metric_value'] / $maxTerritoryValue, 4) : 0.0);

            return $row;
        })->values();

        $customerRows = $customerRows->map(function (array $row) use ($metric): array {
            $row['metric_value'] = match ($metric) {
                'visits' => (float) $row['visits'],
                'sales' => (float) $row['sales'],
                'collections' => (float) $row['collections'],
                default => $row['visited'] ? 1.0 : 0.0,
            };

            return $row;
        });
        $maxCustomerValue = max(0.0, (float) $customerRows->max('metric_value'));
        $points = $customerRows
            ->when(! $actor->hasPermission('customers:view'), fn (Collection $rows) => collect())
            ->filter(fn (array $row) => $row['latitude'] !== null && $row['longitude'] !== null)
            ->map(function (array $row) use ($maxCustomerValue): array {
                $row['intensity'] = $maxCustomerValue > 0
                    ? round($row['metric_value'] / $maxCustomerValue, 4)
                    : 0.0;

                return $row;
            })
            ->values();

        $underCovered = $territoryRows
            ->filter(
                fn (array $row) => $row['customers'] > 0
                    && $row['coverage_percent'] < $underCoveredThreshold
            )
            ->sort(function (array $left, array $right): int {
                $coverage = $left['coverage_percent'] <=> $right['coverage_percent'];

                return $coverage !== 0 ? $coverage : ($right['customers'] <=> $left['customers']);
            })
            ->take(10)
            ->values();

        return [
            'enabled' => true,
            'filters' => [
                'date_from' => $fromDate,
                'date_to' => $toDate,
                'metric' => $metric,
                'currency' => $currency,
            ],
            'timezone' => $timezone,
            'currencies' => $currencies->all(),
            'territories' => $territoryRows->all(),
            'points' => $points->all(),
            'under_covered' => $underCovered->all(),
            'under_covered_threshold_percent' => $underCoveredThreshold,
            'summary' => [
                'territories' => $territoryRows->count(),
                'customers' => $customerRows->count(),
                'mapped_customers' => $points->count(),
                'visited_customers' => $customerRows->where('visited', true)->count(),
                'coverage_percent' => $customerRows->count() > 0
                    ? round(($customerRows->where('visited', true)->count() / $customerRows->count()) * 100, 2)
                    : 0.0,
                'visits' => $customerRows->sum('visits'),
                'sales' => round($customerRows->sum('sales'), 4),
                'collections' => round($customerRows->sum('collections'), 4),
                'currency' => $currency,
            ],
        ];
    }

    private function visibleSalesmanIds(User $actor, string $localDate): Collection
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

    private function visibleTerritories(User $actor, string $localDate, Collection $salesmanIds): Collection
    {
        if (! $actor->hasAnyRole(['supervisor'])) {
            return Territory::active()->orderBy('name')->get();
        }

        if ($salesmanIds->isEmpty()) {
            return collect();
        }

        $assignments = SalesmanAssignment::query()
            ->with('route')
            ->whereIn('salesman_id', $salesmanIds)
            ->current($localDate)
            ->get();
        $territoryIds = $assignments->pluck('territory_id')
            ->merge($assignments->pluck('route.territory_id'))
            ->filter()
            ->unique();
        $branchIds = $assignments
            ->filter(fn (SalesmanAssignment $assignment) => $assignment->territory_id === null && $assignment->route?->territory_id === null)
            ->pluck('branch_id')
            ->filter()
            ->unique();

        return Territory::active()
            ->where(function ($query) use ($territoryIds, $branchIds): void {
                if ($territoryIds->isNotEmpty()) {
                    $query->whereIn('id', $territoryIds);
                }
                if ($branchIds->isNotEmpty()) {
                    $territoryIds->isNotEmpty()
                        ? $query->orWhereIn('branch_id', $branchIds)
                        : $query->whereIn('branch_id', $branchIds);
                }
                if ($territoryIds->isEmpty() && $branchIds->isEmpty()) {
                    $query->whereRaw('1 = 0');
                }
            })
            ->orderBy('name')
            ->get();
    }
}
