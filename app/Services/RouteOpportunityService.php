<?php

namespace App\Services;

use App\Models\Collection;
use App\Models\Customer;
use App\Models\CustomerFollowUp;
use App\Models\CustomerVisit;
use App\Models\Order;
use App\Models\Salesman;
use App\Models\SalesmanAssignment;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection as SupportCollection;

class RouteOpportunityService
{
    public function includedCustomersFor(
        Salesman $salesman,
        CarbonImmutable $localDate,
        array $customerUuids,
        array $excludedCustomerIds = [],
    ): SupportCollection {
        if ($customerUuids === []) {
            return collect();
        }

        $assignment = $this->assignmentFor($salesman, $localDate);

        if (! $assignment) {
            return collect();
        }

        return $this->scopedCustomers($assignment)
            ->whereIn('uuid', array_values(array_unique($customerUuids)))
            ->when(
                $excludedCustomerIds !== [],
                fn (Builder $query) => $query->whereNotIn('id', $excludedCustomerIds),
            )
            ->limit(10)
            ->get();
    }

    public function nearbyFor(
        Salesman $salesman,
        CarbonImmutable $localDate,
        ?array $startLocation,
        array $excludedCustomerIds = [],
        float $radiusKm = 5.0,
        int $limit = 10,
    ): array {
        if (! $this->hasCoordinates($startLocation)) {
            return [];
        }

        $assignment = $this->assignmentFor($salesman, $localDate);

        if (! $assignment) {
            return [];
        }

        $salesman->loadMissing('user.tenant');
        $timezone = $salesman->user?->tenant?->timezone
            ?: config('app.timezone', 'UTC');
        $localDate = $localDate->setTimezone($timezone)->startOfDay();
        $startUtc = $localDate->utc();
        $endUtc = $localDate->addDay()->utc();

        $customers = $this->scopedCustomers($assignment)
            ->when(
                $excludedCustomerIds !== [],
                fn (Builder $query) => $query->whereNotIn('id', $excludedCustomerIds),
            )
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get()
            ->map(function (Customer $customer) use ($startLocation): array {
                $distance = $this->distanceKm(
                    (float) $startLocation['latitude'],
                    (float) $startLocation['longitude'],
                    (float) $customer->latitude,
                    (float) $customer->longitude,
                );

                return [
                    'customer' => $customer,
                    'distance_km' => $distance,
                ];
            })
            ->filter(fn (array $row) => $row['distance_km'] <= $radiusKm)
            ->values();

        if ($customers->isEmpty()) {
            return [];
        }

        $customerIds = $customers->pluck('customer.id')->all();

        $visitedToday = CustomerVisit::query()
            ->where('salesman_id', $salesman->id)
            ->whereIn('customer_id', $customerIds)
            ->where('checked_in_at', '>=', $startUtc)
            ->where('checked_in_at', '<', $endUtc)
            ->pluck('customer_id')
            ->all();

        $customers = $customers
            ->reject(
                fn (array $row) => in_array(
                    $row['customer']->id,
                    $visitedToday,
                    true,
                ),
            )
            ->values();

        if ($customers->isEmpty()) {
            return [];
        }

        $customerIds = $customers->pluck('customer.id')->all();

        $orders = Order::query()
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

        $collections = Collection::query()
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

        $lastVisits = CustomerVisit::query()
            ->where('salesman_id', $salesman->id)
            ->whereIn('customer_id', $customerIds)
            ->where('checked_in_at', '<', $endUtc)
            ->selectRaw('customer_id, MAX(checked_in_at) as last_visited_at')
            ->groupBy('customer_id')
            ->pluck('last_visited_at', 'customer_id');

        return $customers
            ->map(function (array $row) use (
                $orders,
                $collections,
                $followUps,
                $lastVisits,
                $localDate,
                $startUtc,
                $timezone,
            ): array {
                /** @var Customer $customer */
                $customer = $row['customer'];
                $aging = $this->agingForCustomer(
                    $customer->credit_terms_days ?? 30,
                    $orders->get($customer->id, collect()),
                    $collections->get($customer->id, collect()),
                    $localDate,
                );
                $customerFollowUps = $followUps->get(
                    $customer->id,
                    collect(),
                );
                $lastVisitedAt = $lastVisits->get($customer->id);
                $lastVisited = $lastVisitedAt
                    ? CarbonImmutable::parse($lastVisitedAt)
                        ->setTimezone($timezone)
                    : null;

                [$score, $reasons] = $this->score(
                    $aging,
                    $customerFollowUps,
                    $lastVisited,
                    $localDate,
                    $startUtc,
                    (float) $row['distance_km'],
                );

                return [
                    'customer_id' => $customer->uuid,
                    'customer_code' => $customer->code,
                    'customer_name' => $customer->name,
                    'address' => $customer->address,
                    'phone' => $customer->phone,
                    'latitude' => (float) $customer->latitude,
                    'longitude' => (float) $customer->longitude,
                    'distance_km' => round((float) $row['distance_km'], 2),
                    'priority_score' => $score,
                    'priority' => $this->priority($score),
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
                            'overdue' => $followUp->due_at?->lt($startUtc)
                                ?? false,
                        ],
                    )->values()->all(),
                    'can_add_to_route' => true,
                ];
            })
            ->sortBy(fn (array $row): array => [
                -$row['priority_score'],
                $row['distance_km'],
                $row['customer_name'],
            ])
            ->take($limit)
            ->values()
            ->all();
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

    private function scopedCustomers(
        SalesmanAssignment $assignment,
    ): Builder {
        $query = Customer::active();

        if ($assignment->territory_id) {
            return $query->where(
                'territory_id',
                $assignment->territory_id,
            );
        }

        if ($assignment->branch_id) {
            return $query->where('branch_id', $assignment->branch_id);
        }

        return $query->whereRaw('1 = 0');
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
        ?CarbonImmutable $lastVisited,
        CarbonImmutable $localDate,
        CarbonImmutable $startUtc,
        float $distanceKm,
    ): array {
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
                fn (CustomerFollowUp $followUp) => $followUp->due_at?->lt(
                    $startUtc,
                ),
            );
            $hasHigh = $followUps->contains(
                fn (CustomerFollowUp $followUp) => $followUp->priority === 'high',
            );

            $score += $hasOverdue ? 35 : 20;
            $score += $hasHigh ? 15 : 0;
            $reasons[] = $hasOverdue
                ? 'Overdue follow-up'
                : 'Follow-up due today';

            if ($hasHigh) {
                $reasons[] = 'High-priority follow-up';
            }
        }

        if ($lastVisited === null) {
            $score += 15;
            $reasons[] = 'No previous visit recorded';
        } else {
            $days = $lastVisited->startOfDay()->diffInDays($localDate);

            if ($days >= 30) {
                $score += 20;
                $reasons[] = 'Not visited in 30+ days';
            } elseif ($days >= 14) {
                $score += 10;
                $reasons[] = 'Not visited in 14+ days';
            }
        }

        if ($distanceKm <= 1) {
            $score += 15;
            $reasons[] = 'Within 1 km';
        } elseif ($distanceKm <= 3) {
            $score += 10;
            $reasons[] = 'Within 3 km';
        } else {
            $score += 5;
            $reasons[] = 'Nearby opportunity';
        }

        return [$score, $reasons];
    }

    private function priority(int $score): string
    {
        return match (true) {
            $score >= 60 => 'urgent',
            $score >= 40 => 'high',
            $score >= 20 => 'elevated',
            default => 'normal',
        };
    }

    private function hasCoordinates(?array $location): bool
    {
        return $location
            && is_numeric($location['latitude'] ?? null)
            && is_numeric($location['longitude'] ?? null)
            && ! (
                (float) $location['latitude'] === 0.0
                && (float) $location['longitude'] === 0.0
            );
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
