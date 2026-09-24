<?php

namespace App\Services;

use App\Models\Collection;
use App\Models\Customer;
use App\Models\CustomerFollowUp;
use App\Models\CustomerVisit;
use App\Models\Order;
use App\Models\RouteCustomer;
use App\Models\Salesman;
use App\Models\SalesmanAssignment;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection as SupportCollection;

class DailyRoutePlannerService
{
    public const DISTANCE_METHOD = 'straight_line';

    public function __construct(
        private readonly RouteOpportunityService $opportunities,
    ) {}

    public function planFor(
        Salesman $salesman,
        CarbonImmutable $localDate,
        ?array $startLocation = null,
        array $includedCustomerUuids = [],
        float $nearbyRadiusKm = 5.0,
    ): array {
        $salesman->loadMissing('user.tenant');

        $timezone = $salesman->user?->tenant?->timezone
            ?: config('app.timezone', 'UTC');

        $localDate = $localDate->setTimezone($timezone)->startOfDay();
        $startUtc = $localDate->utc();
        $endUtc = $localDate->addDay()->utc();

        $assignment = $this->assignmentFor($salesman, $localDate);
        [$source, $route, $candidates] = $this->candidatesFor($assignment, $localDate);
        $startLocation = $this->normalizeStartLocation($startLocation);

        $plannedCustomerIds = $candidates
            ->pluck('customer')
            ->pluck('id')
            ->all();

        $includedCustomers = $this->opportunities->includedCustomersFor(
            $salesman,
            $localDate,
            $includedCustomerUuids,
            $plannedCustomerIds,
        );

        $nextSequence = $candidates->count() + 1;

        foreach ($includedCustomers as $customer) {
            $candidates->push([
                'customer' => $customer,
                'route_sequence' => null,
                'source_sequence' => $nextSequence++,
                'planned_visit_minutes' => 10,
                'is_opportunity' => true,
            ]);
        }

        $customerIds = $candidates
            ->pluck('customer')
            ->pluck('id')
            ->all();

        $nearbyOpportunities = $this->opportunities->nearbyFor(
            $salesman,
            $localDate,
            $startLocation,
            $customerIds,
            max(0.5, min(25.0, $nearbyRadiusKm)),
        );

        if ($candidates->isEmpty()) {
            return [
                ...$this->basePlan($salesman, $localDate, $assignment),
                'source' => $source,
                'route' => $route,
                'summary' => $this->summary([]),
                'start_location' => $startLocation,
                'stops' => [],
                'nearby_opportunities' => $nearbyOpportunities,
                'dynamic_route' => [
                    'generated_at' => now()->toISOString(),
                    'rerouted_from_current_position' => $startLocation !== null,
                    'included_opportunity_ids' => [],
                    'nearby_radius_km' => max(0.5, min(25.0, $nearbyRadiusKm)),
                ],
                'approximate_air_distance_km' => 0.0,
                'distance_method' => self::DISTANCE_METHOD,
                'warnings' => $this->warnings($route, $localDate, $candidates),
            ];
        }

        $approvedOrders = Order::query()
            ->whereIn('customer_id', $customerIds)
            ->where('payment_type', 'credit')
            ->where('status', 'approved')
            ->orderBy('ordered_at')
            ->get([
                'customer_id',
                'currency',
                'grand_total',
                'ordered_at',
                'due_date',
            ])
            ->groupBy('customer_id');

        $verifiedCollections = Collection::query()
            ->whereIn('customer_id', $customerIds)
            ->where('status', 'verified')
            ->where('collected_at', '<', $endUtc)
            ->selectRaw('customer_id, currency, SUM(amount) as total')
            ->groupBy('customer_id', 'currency')
            ->get()
            ->groupBy('customer_id');

        $followUps = CustomerFollowUp::query()
            ->whereIn('customer_id', $customerIds)
            ->where('status', 'pending')
            ->where(function ($query) use ($salesman): void {
                $query->whereNull('assigned_salesman_id')
                    ->orWhere('assigned_salesman_id', $salesman->id);
            })
            ->where('due_at', '<', $endUtc)
            ->orderBy('due_at')
            ->get()
            ->groupBy('customer_id');

        $visitedToday = CustomerVisit::query()
            ->where('salesman_id', $salesman->id)
            ->whereIn('customer_id', $customerIds)
            ->where('checked_in_at', '>=', $startUtc)
            ->where('checked_in_at', '<', $endUtc)
            ->selectRaw('customer_id, MAX(checked_in_at) as last_visited_at')
            ->groupBy('customer_id')
            ->pluck('last_visited_at', 'customer_id');

        $lastVisits = CustomerVisit::query()
            ->where('salesman_id', $salesman->id)
            ->whereIn('customer_id', $customerIds)
            ->where('checked_in_at', '<', $endUtc)
            ->selectRaw('customer_id, MAX(checked_in_at) as last_visited_at')
            ->groupBy('customer_id')
            ->pluck('last_visited_at', 'customer_id');

        $stops = $candidates->map(function (array $candidate) use (
            $approvedOrders,
            $verifiedCollections,
            $followUps,
            $visitedToday,
            $lastVisits,
            $localDate,
            $startUtc,
            $timezone,
        ): array {
            /** @var Customer $customer */
            $customer = $candidate['customer'];

            $aging = $this->agingForCustomer(
                $customer->credit_terms_days ?? 30,
                $approvedOrders->get($customer->id, collect()),
                $verifiedCollections->get($customer->id, collect()),
                $localDate,
            );

            $customerFollowUps = $followUps->get($customer->id, collect());
            $visited = $visitedToday->has($customer->id);
            $lastVisitedAt = $lastVisits->get($customer->id);
            $lastVisited = $lastVisitedAt
                ? CarbonImmutable::parse($lastVisitedAt)->setTimezone($timezone)
                : null;

            [$score, $reasons] = $this->score(
                $aging,
                $customerFollowUps,
                $visited,
                $lastVisited,
                $localDate,
                $startUtc,
            );

            return [
                'customer_id' => $customer->uuid,
                'customer_code' => $customer->code,
                'customer_name' => $customer->name,
                'address' => $customer->address,
                'phone' => $customer->phone,
                'latitude' => $customer->latitude === null
                    ? null
                    : (float) $customer->latitude,
                'longitude' => $customer->longitude === null
                    ? null
                    : (float) $customer->longitude,
                'route_sequence' => $candidate['route_sequence'],
                'source_sequence' => $candidate['source_sequence'],
                'recommended_order' => null,
                'planned_visit_minutes' => $candidate['planned_visit_minutes'],
                'visited_today' => $visited,
                'last_visited_at' => $lastVisited?->toISOString(),
                'priority_score' => $score,
                'priority' => $this->priority($score, $visited),
                'reasons' => $reasons,
                'overdue' => $aging,
                'due_follow_ups' => $customerFollowUps->map(
                    fn (CustomerFollowUp $followUp) => [
                        'id' => $followUp->uuid,
                        'type' => $followUp->type,
                        'priority' => $followUp->priority,
                        'due_at' => $followUp->due_at
                            ?->setTimezone($timezone)
                            ->toISOString(),
                        'overdue' => $followUp->due_at?->lt($startUtc) ?? false,
                        'notes' => $followUp->notes,
                    ]
                )->values()->all(),
                'distance_from_previous_km' => null,
            ];
        });

        $startLocation = $this->normalizeStartLocation($startLocation);
        [$orderedStops, $totalDistance] = $this->sequenceStops(
            $stops,
            $startLocation,
        );

        return [
            ...$this->basePlan($salesman, $localDate, $assignment),
            'source' => $source,
            'route' => $route,
            'summary' => $this->summary($orderedStops),
            'start_location' => $startLocation,
            'stops' => $orderedStops,
            'approximate_air_distance_km' => round($totalDistance, 2),
            'distance_method' => self::DISTANCE_METHOD,
            'warnings' => $this->warnings($route, $localDate, $candidates),
        ];
    }

    public function planningContextForCustomer(
        Salesman $salesman,
        Customer $customer,
        CarbonImmutable $localDate,
    ): array {
        $salesman->loadMissing('user.tenant');

        $timezone = $salesman->user?->tenant?->timezone
            ?: config('app.timezone', 'UTC');

        $localDate = $localDate->setTimezone($timezone)->startOfDay();
        $assignment = $this->assignmentFor($salesman, $localDate);

        if (! $assignment) {
            return [
                'planned' => false,
                'route_id' => null,
                'source_type' => null,
            ];
        }

        if ($assignment->route_id) {
            $planned = RouteCustomer::query()
                ->where('route_id', $assignment->route_id)
                ->where('customer_id', $customer->id)
                ->exists();

            return [
                'planned' => $planned,
                'route_id' => $planned ? $assignment->route_id : null,
                'source_type' => $planned ? 'route' : null,
            ];
        }

        if (
            $assignment->territory_id
            && (int) $assignment->territory_id === (int) $customer->territory_id
        ) {
            return [
                'planned' => true,
                'route_id' => null,
                'source_type' => 'territory',
            ];
        }

        if (
            $assignment->branch_id
            && ! $assignment->territory_id
            && (int) $assignment->branch_id === (int) $customer->branch_id
        ) {
            return [
                'planned' => true,
                'route_id' => null,
                'source_type' => 'branch',
            ];
        }

        return [
            'planned' => false,
            'route_id' => null,
            'source_type' => null,
        ];
    }

    private function assignmentFor(
        Salesman $salesman,
        CarbonImmutable $localDate,
    ): ?SalesmanAssignment {
        return SalesmanAssignment::with(['branch', 'territory', 'route'])
            ->where('salesman_id', $salesman->id)
            ->current($localDate->toDateString())
            ->latest('effective_from')
            ->first();
    }

    private function candidatesFor(
        ?SalesmanAssignment $assignment,
        CarbonImmutable $localDate,
    ): array {
        if (! $assignment) {
            return [null, null, collect()];
        }

        if ($assignment->route) {
            $route = $assignment->route;
            $route->load([
                'customerMemberships.customer' => fn ($query) => $query
                    ->where('is_active', true),
            ]);

            $candidates = $route->customerMemberships
                ->filter(fn ($membership) => $membership->customer !== null)
                ->values()
                ->map(fn ($membership): array => [
                    'customer' => $membership->customer,
                    'route_sequence' => (int) $membership->sequence_number,
                    'source_sequence' => (int) $membership->sequence_number,
                    'planned_visit_minutes' => (int) $membership->planned_visit_minutes,
                ]);

            return [
                [
                    'type' => 'route',
                    'id' => $route->uuid,
                    'code' => $route->code,
                    'name' => $route->name,
                ],
                $this->routePayload($route, $localDate),
                $candidates,
            ];
        }

        if ($assignment->territory) {
            $customers = Customer::active()
                ->where('territory_id', $assignment->territory_id)
                ->orderBy('name')
                ->get();

            return [
                [
                    'type' => 'territory',
                    'id' => $assignment->territory->uuid,
                    'code' => $assignment->territory->code,
                    'name' => $assignment->territory->name,
                ],
                null,
                $this->customerCandidates($customers),
            ];
        }

        if ($assignment->branch) {
            $customers = Customer::active()
                ->where('branch_id', $assignment->branch_id)
                ->orderBy('name')
                ->get();

            return [
                [
                    'type' => 'branch',
                    'id' => $assignment->branch->uuid,
                    'code' => $assignment->branch->code,
                    'name' => $assignment->branch->name,
                ],
                null,
                $this->customerCandidates($customers),
            ];
        }

        return [null, null, collect()];
    }

    private function customerCandidates(SupportCollection $customers): SupportCollection
    {
        return $customers
            ->values()
            ->map(fn (Customer $customer, int $index): array => [
                'customer' => $customer,
                'route_sequence' => null,
                'source_sequence' => $index + 1,
                'planned_visit_minutes' => 10,
            ]);
    }

    private function basePlan(
        Salesman $salesman,
        CarbonImmutable $localDate,
        ?SalesmanAssignment $assignment,
    ): array {
        return [
            'date' => $localDate->toDateString(),
            'weekday' => strtolower($localDate->format('D')),
            'salesman' => [
                'id' => $salesman->uuid,
                'employee_code' => $salesman->employee_code,
                'name' => $salesman->full_name,
            ],
            'assignment' => $assignment ? [
                'id' => $assignment->uuid,
                'branch' => $assignment->branch?->name,
                'territory' => $assignment->territory?->name,
            ] : null,
        ];
    }

    private function routePayload($route, CarbonImmutable $localDate): array
    {
        $weekday = strtolower($localDate->format('D'));

        return [
            'id' => $route->uuid,
            'code' => $route->code,
            'name' => $route->name,
            'scheduled_today' => in_array(
                $weekday,
                array_map('strtolower', $route->weekdays ?? []),
                true,
            ),
            'weekdays' => $route->weekdays ?? [],
        ];
    }

    private function agingForCustomer(
        int $creditTermsDays,
        SupportCollection $orders,
        SupportCollection $collectionRows,
        CarbonImmutable $asOfDate,
    ): array {
        $verifiedByCurrency = $collectionRows
            ->mapWithKeys(fn ($row) => [
                $row->currency => (float) $row->total,
            ])
            ->all();

        $result = [];

        foreach ($orders->groupBy('currency') as $currency => $currencyOrders) {
            $remainingCollections = (float) ($verifiedByCurrency[$currency] ?? 0);
            $overdue = 0.0;
            $outstanding = 0.0;

            foreach ($currencyOrders as $order) {
                $amount = (float) $order->grand_total;
                $applied = min($remainingCollections, $amount);
                $remainingCollections -= $applied;
                $remaining = max(0, $amount - $applied);

                if ($remaining <= 0) {
                    continue;
                }

                $outstanding += $remaining;

                $dueDate = $order->due_date?->toImmutable()
                    ?? $order->ordered_at?->toImmutable()
                        ->addDays($creditTermsDays);

                if ($dueDate && $dueDate->startOfDay()->lt($asOfDate)) {
                    $overdue += $remaining;
                }
            }

            if ($outstanding > 0) {
                $result[] = [
                    'currency' => $currency,
                    'outstanding' => round($outstanding, 2),
                    'overdue' => round($overdue, 2),
                ];
            }
        }

        return $result;
    }

    private function score(
        array $aging,
        SupportCollection $followUps,
        bool $visited,
        ?CarbonImmutable $lastVisited,
        CarbonImmutable $localDate,
        CarbonImmutable $startUtc,
    ): array {
        if ($visited) {
            return [-100, ['Already visited today']];
        }

        $score = 0;
        $reasons = [];

        foreach ($aging as $row) {
            if ($row['overdue'] > 0) {
                $score += 40;
                $reasons[] = sprintf(
                    '%s %s overdue',
                    $row['currency'],
                    number_format($row['overdue'], 2),
                );
            }
        }

        if ($followUps->isNotEmpty()) {
            $hasOverdue = $followUps->contains(
                fn (CustomerFollowUp $followUp) => $followUp->due_at?->lt($startUtc)
            );
            $hasHigh = $followUps->contains(
                fn (CustomerFollowUp $followUp) => $followUp->priority === 'high'
            );
            $hasPayment = $followUps->contains(
                fn (CustomerFollowUp $followUp) => $followUp->type === 'payment'
            );

            $score += $hasOverdue ? 35 : 20;
            $score += $hasHigh ? 15 : 0;
            $score += $hasPayment ? 5 : 0;

            $reasons[] = $hasOverdue
                ? 'Overdue follow-up'
                : 'Follow-up due today';

            if ($hasHigh) {
                $reasons[] = 'High-priority follow-up';
            }

            if ($hasPayment) {
                $reasons[] = 'Payment follow-up';
            }
        }

        if ($lastVisited === null) {
            $score += 15;
            $reasons[] = 'No previous visit recorded';
        } else {
            $daysSinceVisit = $lastVisited->startOfDay()->diffInDays($localDate);

            if ($daysSinceVisit >= 30) {
                $score += 20;
                $reasons[] = 'Not visited in 30+ days';
            } elseif ($daysSinceVisit >= 14) {
                $score += 10;
                $reasons[] = 'Not visited in 14+ days';
            }
        }

        if ($reasons === []) {
            $reasons[] = 'Regular route stop';
        }

        return [$score, $reasons];
    }

    private function priority(int $score, bool $visited): string
    {
        if ($visited) {
            return 'completed';
        }

        return match (true) {
            $score >= 60 => 'urgent',
            $score >= 40 => 'high',
            $score >= 20 => 'elevated',
            default => 'normal',
        };
    }

    private function sequenceStops(
        SupportCollection $stops,
        ?array $startLocation = null,
    ): array {
        $ordered = collect();
        $previous = $startLocation;
        $totalDistance = 0.0;

        foreach (['urgent', 'high', 'elevated', 'normal'] as $priority) {
            $remaining = $stops
                ->where('visited_today', false)
                ->where('priority', $priority)
                ->values();

            while ($remaining->isNotEmpty()) {
                $next = $this->nextStop($remaining, $previous);
                $distance = $this->distanceBetweenStops($previous, $next);

                if ($distance !== null) {
                    $next['distance_from_previous_km'] = round($distance, 2);
                    $totalDistance += $distance;
                }

                $ordered->push($next);
                $previous = $next;

                $remaining = $remaining
                    ->reject(
                        fn (array $candidate): bool => (
                            $candidate['customer_id'] === $next['customer_id']
                        )
                    )
                    ->values();
            }
        }

        foreach (
            $stops
                ->where('visited_today', true)
                ->sortBy('source_sequence')
                ->values() as $completed
        ) {
            $distance = $this->distanceBetweenStops($previous, $completed);

            if ($distance !== null) {
                $completed['distance_from_previous_km'] = round($distance, 2);
                $totalDistance += $distance;
            }

            $ordered->push($completed);
            $previous = $completed;
        }

        $result = $ordered
            ->values()
            ->map(function (array $stop, int $index): array {
                $stop['recommended_order'] = $index + 1;

                return $stop;
            })
            ->all();

        return [$result, $totalDistance];
    }

    private function nextStop(
        SupportCollection $remaining,
        ?array $previous,
    ): array {
        if (! $this->hasStopCoordinates($previous)) {
            return $remaining
                ->sortBy(fn (array $stop): array => [
                    -$stop['priority_score'],
                    $stop['source_sequence'],
                    $stop['customer_name'],
                ])
                ->first();
        }

        return $remaining
            ->sortBy(function (array $stop) use ($previous): array {
                $distance = $this->distanceBetweenStops($previous, $stop);

                return [
                    $distance ?? PHP_INT_MAX,
                    -$stop['priority_score'],
                    $stop['source_sequence'],
                    $stop['customer_name'],
                ];
            })
            ->first();
    }

    private function normalizeStartLocation(?array $startLocation): ?array
    {
        if (! $startLocation) {
            return null;
        }

        $latitude = $startLocation['latitude'] ?? null;
        $longitude = $startLocation['longitude'] ?? null;

        if (
            ! is_numeric($latitude)
            || ! is_numeric($longitude)
            || (float) $latitude < -90
            || (float) $latitude > 90
            || (float) $longitude < -180
            || (float) $longitude > 180
            || ((float) $latitude === 0.0 && (float) $longitude === 0.0)
        ) {
            return null;
        }

        $accuracy = $startLocation['accuracy'] ?? null;

        if (
            $accuracy !== null
            && (! is_numeric($accuracy) || (float) $accuracy < 0 || (float) $accuracy > 200)
        ) {
            return null;
        }

        return [
            'latitude' => (float) $latitude,
            'longitude' => (float) $longitude,
            'accuracy' => $accuracy === null ? null : (float) $accuracy,
            'source' => (string) ($startLocation['source'] ?? 'provided'),
        ];
    }

    private function distanceBetweenStops(
        ?array $from,
        array $to,
    ): ?float {
        if (! $this->hasStopCoordinates($from) || ! $this->hasStopCoordinates($to)) {
            return null;
        }

        return $this->distanceKm(
            $from['latitude'],
            $from['longitude'],
            $to['latitude'],
            $to['longitude'],
        );
    }

    private function hasStopCoordinates(?array $stop): bool
    {
        if (! $stop) {
            return false;
        }

        return $stop['latitude'] !== null
            && $stop['longitude'] !== null
            && ! (
                (float) $stop['latitude'] === 0.0
                && (float) $stop['longitude'] === 0.0
            );
    }

    private function warnings(
        ?array $route,
        CarbonImmutable $localDate,
        SupportCollection $candidates,
    ): array {
        $warnings = ['distance_estimate_is_straight_line'];

        $missingCoordinates = $candidates->filter(function (array $candidate): bool {
            /** @var Customer $customer */
            $customer = $candidate['customer'];

            return $customer->latitude === null
                || $customer->longitude === null
                || (
                    (float) $customer->latitude === 0.0
                    && (float) $customer->longitude === 0.0
                );
        })->count();

        if ($missingCoordinates > 0) {
            $warnings[] = 'missing_customer_coordinates:'.$missingCoordinates;
        }

        if ($route && ! $route['scheduled_today']) {
            $warnings[] = 'route_not_scheduled_today';
        }

        return $warnings;
    }

    private function summary(array $stops): array
    {
        $collection = collect($stops);

        return [
            'total_stops' => $collection->count(),
            'visited' => $collection->where('visited_today', true)->count(),
            'remaining' => $collection->where('visited_today', false)->count(),
            'urgent' => $collection->where('priority', 'urgent')->count(),
            'high' => $collection->where('priority', 'high')->count(),
            'customers_with_overdue_balance' => $collection
                ->filter(
                    fn (array $stop) => collect($stop['overdue'])->sum('overdue') > 0
                )
                ->count(),
            'customers_with_due_follow_ups' => $collection
                ->filter(fn (array $stop) => $stop['due_follow_ups'] !== [])
                ->count(),
            'planned_visit_minutes' => $collection
                ->where('visited_today', false)
                ->sum('planned_visit_minutes'),
            'missing_coordinates' => $collection
                ->filter(fn (array $stop) => ! $this->hasStopCoordinates($stop))
                ->count(),
        ];
    }

    private function distanceKm(
        float $lat1,
        float $lon1,
        float $lat2,
        float $lon2,
    ): float {
        $earthRadiusKm = 6371.0088;
        $latDelta = deg2rad($lat2 - $lat1);
        $lonDelta = deg2rad($lon2 - $lon1);

        $a = sin($latDelta / 2) ** 2
            + cos(deg2rad($lat1))
            * cos(deg2rad($lat2))
            * sin($lonDelta / 2) ** 2;

        return $earthRadiusKm * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
