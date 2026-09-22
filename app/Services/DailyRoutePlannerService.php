<?php

namespace App\Services;

use App\Models\Collection;
use App\Models\CustomerFollowUp;
use App\Models\CustomerVisit;
use App\Models\Order;
use App\Models\Salesman;
use App\Models\SalesmanAssignment;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection as SupportCollection;

class DailyRoutePlannerService
{
    public function planFor(
        Salesman $salesman,
        CarbonImmutable $localDate,
    ): array {
        $salesman->loadMissing('user.tenant');

        $timezone = $salesman->user?->tenant?->timezone
            ?: config('app.timezone', 'UTC');

        $localDate = $localDate->setTimezone($timezone)->startOfDay();
        $startUtc = $localDate->utc();
        $endUtc = $localDate->addDay()->utc();

        $assignment = SalesmanAssignment::with(['branch', 'territory', 'route'])
            ->where('salesman_id', $salesman->id)
            ->current($localDate->toDateString())
            ->latest('effective_from')
            ->first();

        if (! $assignment?->route) {
            return $this->emptyPlan($salesman, $localDate, $assignment);
        }

        $route = $assignment->route;
        $route->load([
            'customerMemberships.customer' => fn ($query) => $query->where('is_active', true),
        ]);

        $memberships = $route->customerMemberships
            ->filter(fn ($membership) => $membership->customer !== null)
            ->values();

        $customerIds = $memberships->pluck('customer_id')->all();

        if ($customerIds === []) {
            return [
                ...$this->basePlan($salesman, $localDate, $assignment),
                'route' => $this->routePayload($route, $localDate),
                'summary' => $this->summary([]),
                'stops' => [],
                'approximate_air_distance_km' => 0.0,
            ];
        }

        $approvedOrders = Order::query()
            ->whereIn('customer_id', $customerIds)
            ->where('payment_type', 'credit')
            ->where('status', 'approved')
            ->orderBy('ordered_at')
            ->get(['customer_id', 'currency', 'grand_total', 'ordered_at', 'due_date'])
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

        $stops = $memberships->map(function ($membership) use (
            $approvedOrders,
            $verifiedCollections,
            $followUps,
            $visitedToday,
            $lastVisits,
            $localDate,
            $startUtc,
            $timezone,
        ): array {
            $customer = $membership->customer;
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
                'latitude' => $customer->latitude === null ? null : (float) $customer->latitude,
                'longitude' => $customer->longitude === null ? null : (float) $customer->longitude,
                'route_sequence' => (int) $membership->sequence_number,
                'recommended_order' => null,
                'planned_visit_minutes' => (int) $membership->planned_visit_minutes,
                'visited_today' => $visited,
                'last_visited_at' => $lastVisited?->toISOString(),
                'priority_score' => $score,
                'priority' => $this->priority($score, $visited),
                'reasons' => $reasons,
                'overdue' => $aging,
                'due_follow_ups' => $customerFollowUps->map(fn (CustomerFollowUp $followUp) => [
                    'id' => $followUp->uuid,
                    'type' => $followUp->type,
                    'priority' => $followUp->priority,
                    'due_at' => $followUp->due_at?->setTimezone($timezone)->toISOString(),
                    'overdue' => $followUp->due_at?->lt($startUtc) ?? false,
                    'notes' => $followUp->notes,
                ])->values()->all(),
                'distance_from_previous_km' => null,
            ];
        })->all();

        usort($stops, function (array $left, array $right): int {
            if ($left['visited_today'] !== $right['visited_today']) {
                return $left['visited_today'] <=> $right['visited_today'];
            }

            if ($left['priority_score'] !== $right['priority_score']) {
                return $right['priority_score'] <=> $left['priority_score'];
            }

            return $left['route_sequence'] <=> $right['route_sequence'];
        });

        $previous = null;
        $totalDistance = 0.0;

        foreach ($stops as $index => &$stop) {
            $stop['recommended_order'] = $index + 1;

            if (
                $previous !== null
                && $previous['latitude'] !== null
                && $previous['longitude'] !== null
                && $stop['latitude'] !== null
                && $stop['longitude'] !== null
            ) {
                $distance = $this->distanceKm(
                    $previous['latitude'],
                    $previous['longitude'],
                    $stop['latitude'],
                    $stop['longitude'],
                );
                $stop['distance_from_previous_km'] = round($distance, 2);
                $totalDistance += $distance;
            }

            if ($stop['latitude'] !== null && $stop['longitude'] !== null) {
                $previous = $stop;
            }
        }
        unset($stop);

        return [
            ...$this->basePlan($salesman, $localDate, $assignment),
            'route' => $this->routePayload($route, $localDate),
            'summary' => $this->summary($stops),
            'stops' => $stops,
            'approximate_air_distance_km' => round($totalDistance, 2),
        ];
    }

    private function emptyPlan(
        Salesman $salesman,
        CarbonImmutable $localDate,
        ?SalesmanAssignment $assignment,
    ): array {
        return [
            ...$this->basePlan($salesman, $localDate, $assignment),
            'route' => null,
            'summary' => $this->summary([]),
            'stops' => [],
            'approximate_air_distance_km' => 0.0,
        ];
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
            'scheduled_today' => in_array($weekday, $route->weekdays ?? [], true),
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
            ->mapWithKeys(fn ($row) => [$row->currency => (float) $row->total])
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
                    ?? $order->ordered_at?->toImmutable()->addDays($creditTermsDays);

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
                ->filter(fn (array $stop) => collect($stop['overdue'])->sum('overdue') > 0)
                ->count(),
            'customers_with_due_follow_ups' => $collection
                ->filter(fn (array $stop) => $stop['due_follow_ups'] !== [])
                ->count(),
            'planned_visit_minutes' => $collection
                ->where('visited_today', false)
                ->sum('planned_visit_minutes'),
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
