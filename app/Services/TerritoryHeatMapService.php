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
    public const METRICS = [
        'coverage',
        'visits',
        'density',
        'stale',
        'sales',
        'collections',
    ];

    public function __construct(
        private readonly TenantClock $clock,
        private readonly FieldIntelligenceSettingsService $settings,
        private readonly TerritoryLocator $locator,
    ) {}

    public function build(User $actor, array $filters = []): array
    {
        $actor->loadMissing(['tenant', 'supervisor']);
        $timezone = $this->clock->timezone($actor->tenant);
        $today = $this->clock->now($actor->tenant)->toDateString();
        $toDate = (string) ($filters['date_to'] ?? $today);
        $fromDate = (string) (
            $filters['date_from']
            ?? CarbonImmutable::parse($toDate, $timezone)
                ->subDays(29)
                ->toDateString()
        );
        $start = CarbonImmutable::parse(
            $fromDate.' 00:00:00',
            $timezone,
        )->utc();
        $end = CarbonImmutable::parse(
            $toDate.' 00:00:00',
            $timezone,
        )->addDay()->utc();
        $metric = in_array($filters['metric'] ?? null, self::METRICS, true)
            ? (string) $filters['metric']
            : 'coverage';

        $featureSettings = $this->settings->settingsFor($actor->tenant);
        $underCoveredThreshold = (int) $featureSettings[
            'territory_under_covered_threshold_percent'
        ];
        $staleDays = (int) $featureSettings['territory_stale_customer_days'];
        $staleAttentionThreshold = (int) $featureSettings[
            'territory_stale_attention_percent'
        ];
        $geometryAuditEnabled = (bool) $featureSettings[
            'territory_geometry_audit_enabled'
        ];

        if (! $featureSettings['territory_heat_map_enabled']) {
            return $this->disabledPayload(
                $fromDate,
                $toDate,
                $metric,
                $filters,
                $timezone,
                $underCoveredThreshold,
                $staleDays,
                $staleAttentionThreshold,
                $geometryAuditEnabled,
            );
        }

        $localDate = CarbonImmutable::parse($toDate, $timezone)->toDateString();
        $salesmanIds = $this->visibleSalesmanIds($actor, $localDate);
        $territories = $this->visibleTerritories(
            $actor,
            $localDate,
            $salesmanIds,
        );
        $territoryIds = $territories->pluck('id');
        $territoriesById = $territories->keyBy('id');
        $branchIds = $territories
            ->pluck('branch_id')
            ->filter()
            ->unique()
            ->values();

        $visibleCustomersQuery = Customer::active()
            ->orderBy('name');

        if ($actor->hasAnyRole(['supervisor'])) {
            $branchIds->isEmpty()
                ? $visibleCustomersQuery->whereRaw('1 = 0')
                : $visibleCustomersQuery->whereIn('branch_id', $branchIds);
        }

        $visibleCustomers = $visibleCustomersQuery->get([
            'id',
            'uuid',
            'branch_id',
            'territory_id',
            'code',
            'name',
            'latitude',
            'longitude',
        ]);
        $customers = $visibleCustomers
            ->whereIn('territory_id', $territoryIds)
            ->values();
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

        $lastVisits = CustomerVisit::query()
            ->whereIn('salesman_id', $salesmanIds)
            ->whereIn('customer_id', $customerIds)
            ->where('status', 'completed')
            ->where('checked_in_at', '<', $end)
            ->selectRaw(
                'customer_id, MAX(checked_in_at) as last_visited_at'
            )
            ->groupBy('customer_id')
            ->pluck('last_visited_at', 'customer_id');

        $staleCutoff = CarbonImmutable::parse(
            $toDate.' 23:59:59',
            $timezone,
        )->subDays($staleDays)->utc();

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
            $lastVisits,
            $territoriesById,
            $currency,
            $staleCutoff,
            $geometryAuditEnabled,
        ): array {
            $customerOrders = $ordersByCustomer->get(
                $customer->id,
                collect(),
            );
            $customerCollections = $collectionsByCustomer->get(
                $customer->id,
                collect(),
            );
            $customerVisits = $visitsByCustomer->get(
                $customer->id,
                collect(),
            );
            $sales = round(
                $customerOrders
                    ->where('currency', $currency)
                    ->sum(fn (Order $order) => (float) $order->grand_total),
                4,
            );
            $collected = round(
                $customerCollections
                    ->where('currency', $currency)
                    ->sum(
                        fn (CustomerCollection $collection) => (
                            (float) $collection->amount
                        )
                    ),
                4,
            );
            $lastVisitedValue = $lastVisits->get($customer->id);
            $lastVisited = $lastVisitedValue
                ? CarbonImmutable::parse($lastVisitedValue)
                : null;
            $mapped = $this->mapped($customer);
            $territory = $territoriesById->get($customer->territory_id);
            $outsidePolygon = $geometryAuditEnabled
                && $mapped
                && $territory
                && filled($territory->polygon)
                && ! $this->locator->contains(
                    $territory,
                    (float) $customer->latitude,
                    (float) $customer->longitude,
                );

            return [
                'id' => $customer->uuid,
                'territory_id' => $customer->territory_id,
                'code' => $customer->code,
                'name' => $customer->name,
                'latitude' => $customer->latitude === null
                    ? null
                    : (float) $customer->latitude,
                'longitude' => $customer->longitude === null
                    ? null
                    : (float) $customer->longitude,
                'mapped' => $mapped,
                'visits' => $customerVisits->count(),
                'visited' => $customerVisits->isNotEmpty(),
                'last_visited_at' => $lastVisited?->toIso8601String(),
                'stale' => $lastVisited === null || $lastVisited->lt($staleCutoff),
                'outside_polygon' => $outsidePolygon,
                'orders' => $customerOrders->where('currency', $currency)->count(),
                'sales' => $sales,
                'collections' => $collected,
            ];
        });

        $territoryRows = $territories->map(function (Territory $territory) use (
            $customerRows,
            $metric,
            $currency,
            $underCoveredThreshold,
            $staleAttentionThreshold,
        ): array {
            $rows = $customerRows->where(
                'territory_id',
                $territory->id,
            );
            $customers = $rows->count();
            $visitedCustomers = $rows->where('visited', true)->count();
            $coverage = $customers > 0
                ? round(($visitedCustomers / $customers) * 100, 2)
                : 0.0;
            $staleCustomers = $rows->where('stale', true)->count();
            $stalePercent = $customers > 0
                ? round(($staleCustomers / $customers) * 100, 2)
                : 0.0;
            $visits = $rows->sum('visits');
            $sales = round($rows->sum('sales'), 4);
            $collections = round($rows->sum('collections'), 4);
            $areaKm2 = $this->polygonAreaKm2($territory->polygon);
            $density = $areaKm2 !== null && $areaKm2 > 0
                ? round($customers / $areaKm2, 2)
                : null;
            $outsidePolygon = $rows->where('outside_polygon', true)->count();
            $unmapped = $rows->where('mapped', false)->count();
            $attentionReasons = [];

            if ($customers > 0 && $coverage < $underCoveredThreshold) {
                $attentionReasons[] = 'coverage_below_threshold';
            }

            if (
                $customers > 0
                && $stalePercent >= $staleAttentionThreshold
            ) {
                $attentionReasons[] = 'stale_customer_share_high';
            }

            if ($outsidePolygon > 0) {
                $attentionReasons[] = 'customer_coordinates_outside_polygon';
            }

            if ($unmapped > 0) {
                $attentionReasons[] = 'customers_missing_coordinates';
            }

            $value = match ($metric) {
                'visits' => (float) $visits,
                'density' => (float) ($density ?? 0),
                'stale' => $stalePercent,
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
                'area_km2' => $areaKm2,
                'customers' => $customers,
                'mapped_customers' => $rows->where('mapped', true)->count(),
                'unmapped_customers' => $unmapped,
                'visited_customers' => $visitedCustomers,
                'coverage_percent' => $coverage,
                'stale_customers' => $staleCustomers,
                'stale_percent' => $stalePercent,
                'outside_polygon_customers' => $outsidePolygon,
                'customer_density_per_km2' => $density,
                'visits' => $visits,
                'visits_per_customer' => $customers > 0
                    ? round($visits / $customers, 2)
                    : 0.0,
                'sales' => $sales,
                'sales_per_customer' => $customers > 0
                    ? round($sales / $customers, 4)
                    : 0.0,
                'collections' => $collections,
                'collections_per_customer' => $customers > 0
                    ? round($collections / $customers, 4)
                    : 0.0,
                'currency' => $currency,
                'attention_required' => $attentionReasons !== [],
                'attention_reasons' => $attentionReasons,
                'metric' => $metric,
                'metric_value' => $value,
            ];
        });

        $maxTerritoryValue = max(
            0.0,
            (float) $territoryRows->max('metric_value'),
        );
        $territoryRows = $territoryRows
            ->map(
                function (array $row) use (
                    $metric,
                    $maxTerritoryValue,
                ): array {
                    $row['intensity'] = in_array(
                        $metric,
                        ['coverage', 'stale'],
                        true,
                    )
                        ? round(
                            min(
                                1,
                                max(0, $row[$metric.'_percent'] / 100),
                            ),
                            4,
                        )
                        : (
                            $maxTerritoryValue > 0
                                ? round(
                                    $row['metric_value'] / $maxTerritoryValue,
                                    4,
                                )
                                : 0.0
                        );

                    return $row;
                }
            )
            ->values();

        $customerRows = $customerRows->map(
            function (array $row) use ($metric): array {
                $row['metric_value'] = match ($metric) {
                    'visits' => (float) $row['visits'],
                    'density' => 1.0,
                    'stale' => $row['stale'] ? 1.0 : 0.0,
                    'sales' => (float) $row['sales'],
                    'collections' => (float) $row['collections'],
                    default => $row['visited'] ? 1.0 : 0.0,
                };

                return $row;
            }
        );
        $maxCustomerValue = max(
            0.0,
            (float) $customerRows->max('metric_value'),
        );
        $points = $customerRows
            ->when(
                ! $actor->hasPermission('customers:view'),
                fn (Collection $rows) => collect(),
            )
            ->filter(
                fn (array $row) => (
                    $row['latitude'] !== null
                    && $row['longitude'] !== null
                )
            )
            ->map(
                function (array $row) use ($maxCustomerValue): array {
                    $row['intensity'] = $maxCustomerValue > 0
                        ? round(
                            $row['metric_value'] / $maxCustomerValue,
                            4,
                        )
                        : 0.0;

                    return $row;
                }
            )
            ->values();

        $underCovered = $territoryRows
            ->filter(
                fn (array $row) => (
                    $row['customers'] > 0
                    && $row['coverage_percent'] < $underCoveredThreshold
                )
            )
            ->sort(function (array $left, array $right): int {
                $coverage = (
                    $left['coverage_percent']
                    <=> $right['coverage_percent']
                );

                return $coverage !== 0
                    ? $coverage
                    : ($right['customers'] <=> $left['customers']);
            })
            ->take(10)
            ->values();

        $attention = $territoryRows
            ->filter(fn (array $row) => $row['attention_required'])
            ->sort(function (array $left, array $right): int {
                $reasons = count($right['attention_reasons'])
                    <=> count($left['attention_reasons']);

                if ($reasons !== 0) {
                    return $reasons;
                }

                $stale = $right['stale_percent'] <=> $left['stale_percent'];

                return $stale !== 0
                    ? $stale
                    : ($left['coverage_percent'] <=> $right['coverage_percent']);
            })
            ->take(12)
            ->values();

        $unassigned = $geometryAuditEnabled
            ? $visibleCustomers
                ->whereNull('territory_id')
                ->values()
            : collect();
        $outsidePolygon = $geometryAuditEnabled
            ? $customerRows
                ->where('outside_polygon', true)
                ->values()
            : collect();
        $staleCustomers = $customerRows
            ->where('stale', true)
            ->sortBy('last_visited_at')
            ->values();
        $showCustomerDetails = $actor->hasPermission('customers:view');

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
            'attention' => $attention->all(),
            'unassigned_customers' => $showCustomerDetails
                ? $unassigned->take(25)->map(
                    fn (Customer $customer) => [
                        'id' => $customer->uuid,
                        'code' => $customer->code,
                        'name' => $customer->name,
                        'latitude' => $customer->latitude === null
                            ? null
                            : (float) $customer->latitude,
                        'longitude' => $customer->longitude === null
                            ? null
                            : (float) $customer->longitude,
                    ]
                )->all()
                : [],
            'outside_polygon_customers' => $showCustomerDetails
                ? $outsidePolygon->take(25)->map(
                    function (array $row) use ($territoriesById): array {
                        return [
                            'id' => $row['id'],
                            'code' => $row['code'],
                            'name' => $row['name'],
                            'territory' => $territoriesById
                                ->get($row['territory_id'])
                                ?->name,
                            'latitude' => $row['latitude'],
                            'longitude' => $row['longitude'],
                        ];
                    }
                )->all()
                : [],
            'stale_customers' => $showCustomerDetails
                ? $staleCustomers->take(25)->map(
                    function (array $row) use ($territoriesById): array {
                        return [
                            'id' => $row['id'],
                            'code' => $row['code'],
                            'name' => $row['name'],
                            'territory' => $territoriesById
                                ->get($row['territory_id'])
                                ?->name,
                            'last_visited_at' => $row['last_visited_at'],
                        ];
                    }
                )->all()
                : [],
            'under_covered_threshold_percent' => $underCoveredThreshold,
            'stale_customer_days' => $staleDays,
            'stale_attention_percent' => $staleAttentionThreshold,
            'geometry_audit_enabled' => $geometryAuditEnabled,
            'summary' => [
                'territories' => $territoryRows->count(),
                'active_visible_customers' => $visibleCustomers->count(),
                'customers' => $customerRows->count(),
                'mapped_customers' => $customerRows
                    ->where('mapped', true)
                    ->count(),
                'unmapped_customers' => $customerRows
                    ->where('mapped', false)
                    ->count(),
                'visited_customers' => $customerRows
                    ->where('visited', true)
                    ->count(),
                'coverage_percent' => $customerRows->count() > 0
                    ? round(
                        (
                            $customerRows->where('visited', true)->count()
                            / $customerRows->count()
                        ) * 100,
                        2,
                    )
                    : 0.0,
                'stale_customers' => $staleCustomers->count(),
                'stale_percent' => $customerRows->count() > 0
                    ? round(
                        (
                            $staleCustomers->count()
                            / $customerRows->count()
                        ) * 100,
                        2,
                    )
                    : 0.0,
                'unassigned_customers' => $unassigned->count(),
                'outside_polygon_customers' => $outsidePolygon->count(),
                'territories_needing_attention' => $attention->count(),
                'visits' => $customerRows->sum('visits'),
                'sales' => round($customerRows->sum('sales'), 4),
                'collections' => round(
                    $customerRows->sum('collections'),
                    4,
                ),
                'currency' => $currency,
            ],
        ];
    }

    private function disabledPayload(
        string $fromDate,
        string $toDate,
        string $metric,
        array $filters,
        string $timezone,
        int $underCoveredThreshold,
        int $staleDays,
        int $staleAttentionThreshold,
        bool $geometryAuditEnabled,
    ): array {
        $currency = strtoupper(
            (string) ($filters['currency'] ?? 'AFN')
        ) ?: 'AFN';

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
            'attention' => [],
            'unassigned_customers' => [],
            'outside_polygon_customers' => [],
            'stale_customers' => [],
            'under_covered_threshold_percent' => $underCoveredThreshold,
            'stale_customer_days' => $staleDays,
            'stale_attention_percent' => $staleAttentionThreshold,
            'geometry_audit_enabled' => $geometryAuditEnabled,
            'summary' => [
                'territories' => 0,
                'active_visible_customers' => 0,
                'customers' => 0,
                'mapped_customers' => 0,
                'unmapped_customers' => 0,
                'visited_customers' => 0,
                'coverage_percent' => 0.0,
                'stale_customers' => 0,
                'stale_percent' => 0.0,
                'unassigned_customers' => 0,
                'outside_polygon_customers' => 0,
                'territories_needing_attention' => 0,
                'visits' => 0,
                'sales' => 0.0,
                'collections' => 0.0,
                'currency' => $currency,
            ],
        ];
    }

    private function mapped(Customer $customer): bool
    {
        if ($customer->latitude === null || $customer->longitude === null) {
            return false;
        }

        return ! (
            (float) $customer->latitude === 0.0
            && (float) $customer->longitude === 0.0
        );
    }

    private function polygonAreaKm2(mixed $geometry): ?float
    {
        $rings = $this->outerRings($geometry);

        if ($rings === []) {
            return null;
        }

        $area = collect($rings)
            ->sum(fn (array $ring) => $this->ringAreaKm2($ring));

        return $area > 0 ? round($area, 4) : null;
    }

    private function outerRings(mixed $geometry): array
    {
        if (! is_array($geometry) || $geometry === []) {
            return [];
        }

        if (
            array_is_list($geometry)
            && isset($geometry[0])
            && is_array($geometry[0])
        ) {
            return [
                collect($geometry)
                    ->filter(
                        fn ($point) => (
                            is_array($point)
                            && count($point) >= 2
                        )
                    )
                    ->map(
                        fn ($point) => [
                            (float) $point[0],
                            (float) $point[1],
                        ]
                    )
                    ->values()
                    ->all(),
            ];
        }

        $type = $geometry['type'] ?? null;
        $coordinates = $geometry['coordinates'] ?? null;

        if (! is_array($coordinates)) {
            return [];
        }

        if ($type === 'Polygon') {
            $outer = $coordinates[0] ?? [];

            return [$this->geoJsonRingToLatLng($outer)];
        }

        if ($type === 'MultiPolygon') {
            return collect($coordinates)
                ->map(
                    fn ($polygon) => $this->geoJsonRingToLatLng(
                        is_array($polygon)
                            ? ($polygon[0] ?? [])
                            : [],
                    )
                )
                ->filter(fn (array $ring) => count($ring) >= 3)
                ->values()
                ->all();
        }

        return [];
    }

    private function geoJsonRingToLatLng(mixed $ring): array
    {
        if (! is_array($ring)) {
            return [];
        }

        return collect($ring)
            ->filter(
                fn ($point) => (
                    is_array($point)
                    && count($point) >= 2
                )
            )
            ->map(
                fn ($point) => [
                    (float) $point[1],
                    (float) $point[0],
                ]
            )
            ->values()
            ->all();
    }

    private function ringAreaKm2(array $ring): float
    {
        if (count($ring) < 3) {
            return 0.0;
        }

        $earthRadiusKm = 6371.0088;
        $meanLat = collect($ring)->avg(
            fn (array $point) => $point[0]
        );
        $cosLat = cos(deg2rad((float) $meanLat));
        $points = collect($ring)
            ->map(
                fn (array $point) => [
                    $earthRadiusKm
                        * deg2rad((float) $point[1])
                        * $cosLat,
                    $earthRadiusKm
                        * deg2rad((float) $point[0]),
                ]
            )
            ->values()
            ->all();

        $area = 0.0;
        $count = count($points);

        for ($index = 0; $index < $count; $index++) {
            $next = ($index + 1) % $count;
            $area += (
                $points[$index][0] * $points[$next][1]
                - $points[$next][0] * $points[$index][1]
            );
        }

        return abs($area) / 2;
    }

    private function visibleSalesmanIds(
        User $actor,
        string $localDate,
    ): Collection {
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

    private function visibleTerritories(
        User $actor,
        string $localDate,
        Collection $salesmanIds,
    ): Collection {
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
            ->filter(
                fn (SalesmanAssignment $assignment) => (
                    $assignment->territory_id === null
                    && $assignment->route?->territory_id === null
                )
            )
            ->pluck('branch_id')
            ->filter()
            ->unique();

        return Territory::active()
            ->where(
                function ($query) use (
                    $territoryIds,
                    $branchIds,
                ): void {
                    if ($territoryIds->isNotEmpty()) {
                        $query->whereIn('id', $territoryIds);
                    }

                    if ($branchIds->isNotEmpty()) {
                        $territoryIds->isNotEmpty()
                            ? $query->orWhereIn('branch_id', $branchIds)
                            : $query->whereIn('branch_id', $branchIds);
                    }

                    if (
                        $territoryIds->isEmpty()
                        && $branchIds->isEmpty()
                    ) {
                        $query->whereRaw('1 = 0');
                    }
                }
            )
            ->orderBy('name')
            ->get();
    }
}
