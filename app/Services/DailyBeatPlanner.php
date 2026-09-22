<?php

namespace App\Services;

use App\Models\Collection as PaymentCollection;
use App\Models\Customer;
use App\Models\CustomerFollowUp;
use App\Models\CustomerVisit;
use App\Models\DailyBeatPlan;
use App\Models\Order;
use App\Models\RouteCustomer;
use App\Models\Salesman;
use App\Models\SalesmanAssignment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DailyBeatPlanner
{
    public const ALGORITHM_VERSION = 'offline-priority-v1';

    public function generate(
        Salesman $salesman,
        CarbonImmutable $date,
        User $actor,
    ): DailyBeatPlan {
        $actor->loadMissing('tenant');
        $timezone = $actor->tenant?->timezone ?: config('app.timezone', 'UTC');
        $localDate = $date->setTimezone($timezone)->startOfDay();

        $assignment = SalesmanAssignment::with('route')
            ->where('salesman_id', $salesman->id)
            ->current($localDate->toDateString())
            ->latest('effective_from')
            ->first();

        if (! $assignment) {
            throw ValidationException::withMessages([
                'salesman' => 'This salesman has no active assignment for the selected date.',
            ]);
        }

        [$sourceType, $candidates] = $this->candidates($assignment);

        if ($candidates->isEmpty()) {
            throw ValidationException::withMessages([
                'salesman' => 'No active customers are available from this salesman assignment.',
            ]);
        }

        $signals = $this->signals(
            $candidates->pluck('customer'),
            $salesman,
            $localDate,
            $timezone,
        );

        $ranked = $candidates->map(function (array $candidate) use ($signals): array {
            $customerSignals = $signals[$candidate['customer']->id] ?? [
                'score' => 0,
                'tier' => 'routine',
                'reason_codes' => [],
                'signals' => [],
            ];

            return [
                ...$candidate,
                ...$customerSignals,
            ];
        });

        $sequenced = $this->sequence($ranked);
        $completedVisits = $this->completedVisits(
            $salesman,
            $localDate,
            $timezone,
        );

        $warnings = ['distance_estimate_is_straight_line'];
        $missingCoordinates = $sequenced->filter(
            fn (array $candidate): bool => ! $this->hasCoordinates($candidate['customer'])
        )->count();

        if ($missingCoordinates > 0) {
            $warnings[] = 'missing_customer_coordinates:'.$missingCoordinates;
        }

        if (
            $assignment->route
            && is_array($assignment->route->weekdays)
            && $assignment->route->weekdays !== []
            && ! in_array(
                strtolower($localDate->format('l')),
                array_map('strtolower', $assignment->route->weekdays),
                true,
            )
        ) {
            $warnings[] = 'route_not_scheduled_for_weekday';
        }

        return DB::transaction(function () use (
            $salesman,
            $assignment,
            $sourceType,
            $localDate,
            $actor,
            $sequenced,
            $completedVisits,
            $warnings,
            $missingCoordinates,
        ): DailyBeatPlan {
            $plan = DailyBeatPlan::updateOrCreate(
                [
                    'salesman_id' => $salesman->id,
                    'plan_date' => $localDate->toDateString(),
                ],
                [
                    'assignment_id' => $assignment->id,
                    'route_id' => $assignment->route_id,
                    'status' => DailyBeatPlan::STATUS_PUBLISHED,
                    'source_type' => $sourceType,
                    'algorithm_version' => self::ALGORITHM_VERSION,
                    'generated_by' => $actor->id,
                    'generated_at' => now(),
                    'warnings' => $warnings,
                ],
            );

            $plan->stops()->delete();

            $totalDistance = 0;
            $totalMinutes = 0;

            foreach ($sequenced as $index => $candidate) {
                $customer = $candidate['customer'];
                $completedVisit = $completedVisits->get($customer->id);
                $distance = $candidate['distance_from_previous_m'];

                if ($distance !== null) {
                    $totalDistance += $distance;
                }

                $totalMinutes += $candidate['planned_visit_minutes'];

                $plan->stops()->create([
                    'customer_id' => $customer->id,
                    'source_route_sequence' => $candidate['route_sequence'],
                    'sequence_number' => $index + 1,
                    'priority_tier' => $candidate['tier'],
                    'priority_score' => $candidate['score'],
                    'reason_codes' => $candidate['reason_codes'],
                    'signals' => $candidate['signals'],
                    'estimated_distance_from_previous_m' => $distance,
                    'planned_visit_minutes' => $candidate['planned_visit_minutes'],
                    'completed_visit_id' => $completedVisit?->id,
                    'completed_at' => $completedVisit?->checked_out_at,
                ]);
            }

            $plan->update([
                'total_stops' => $sequenced->count(),
                'planned_visit_minutes' => $totalMinutes,
                'estimated_distance_m' => $totalDistance,
                'missing_coordinates' => $missingCoordinates,
            ]);

            return $plan->fresh()->load([
                'salesman.user',
                'route',
                'stops.customer',
                'stops.completedVisit',
            ]);
        });
    }

    private function candidates(SalesmanAssignment $assignment): array
    {
        if ($assignment->route_id) {
            $memberships = RouteCustomer::with('customer')
                ->where('route_id', $assignment->route_id)
                ->whereHas('customer', fn ($query) => $query->active())
                ->orderBy('sequence_number')
                ->get();

            return [
                'route',
                $memberships->map(fn (RouteCustomer $membership): array => [
                    'customer' => $membership->customer,
                    'route_sequence' => (int) $membership->sequence_number,
                    'planned_visit_minutes' => (int) $membership->planned_visit_minutes,
                ]),
            ];
        }

        if ($assignment->territory_id) {
            return [
                'territory',
                Customer::active()
                    ->where('territory_id', $assignment->territory_id)
                    ->orderBy('name')
                    ->get()
                    ->map(fn (Customer $customer): array => [
                        'customer' => $customer,
                        'route_sequence' => null,
                        'planned_visit_minutes' => 10,
                    ]),
            ];
        }

        if ($assignment->branch_id) {
            return [
                'branch',
                Customer::active()
                    ->where('branch_id', $assignment->branch_id)
                    ->orderBy('name')
                    ->get()
                    ->map(fn (Customer $customer): array => [
                        'customer' => $customer,
                        'route_sequence' => null,
                        'planned_visit_minutes' => 10,
                    ]),
            ];
        }

        throw ValidationException::withMessages([
            'salesman' => 'The active assignment does not contain a route, territory, or branch.',
        ]);
    }

    private function signals(
        Collection $customers,
        Salesman $salesman,
        CarbonImmutable $date,
        string $timezone,
    ): array {
        $customerIds = $customers->pluck('id')->all();
        $startUtc = $date->startOfDay()->utc();
        $endUtc = $date->endOfDay()->utc();

        $followUps = CustomerFollowUp::query()
            ->whereIn('customer_id', $customerIds)
            ->where('status', 'pending')
            ->where('due_at', '<=', $endUtc)
            ->get()
            ->groupBy('customer_id');

        $lastVisits = CustomerVisit::query()
            ->whereIn('customer_id', $customerIds)
            ->where('salesman_id', $salesman->id)
            ->selectRaw('customer_id, MAX(checked_in_at) as last_visit_at')
            ->groupBy('customer_id')
            ->pluck('last_visit_at', 'customer_id');

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

        $collections = PaymentCollection::query()
            ->whereIn('customer_id', $customerIds)
            ->where('status', 'verified')
            ->where('collected_at', '<=', $endUtc)
            ->selectRaw('customer_id, currency, SUM(amount) as total')
            ->groupBy('customer_id', 'currency')
            ->get()
            ->mapWithKeys(fn ($row) => [
                $row->customer_id.'|'.$row->currency => (float) $row->total,
            ]);

        $result = [];

        foreach ($customers as $customer) {
            $score = 0;
            $reasons = [];
            $data = [];

            $overdue = $this->overdueBalances(
                $customer,
                $orders->get($customer->id, collect()),
                $collections,
                $date,
                $timezone,
            );

            if ($overdue !== []) {
                $score += 35;
                $reasons[] = 'overdue_credit';
                $data['overdue'] = $overdue;
            }

            $dueFollowUps = $followUps->get($customer->id, collect());

            if ($dueFollowUps->isNotEmpty()) {
                $followUpScore = $dueFollowUps->map(function (CustomerFollowUp $followUp): int {
                    $priority = match ($followUp->priority) {
                        'high' => 30,
                        'normal' => 20,
                        default => 10,
                    };

                    return $priority + ($followUp->type === 'payment' ? 10 : 0);
                })->max();

                $score += min(40, (int) $followUpScore);
                $reasons[] = 'follow_up_due';

                if ($dueFollowUps->contains('type', 'payment')) {
                    $reasons[] = 'payment_follow_up';
                }

                $data['due_follow_ups'] = $dueFollowUps->count();
            }

            $lastVisitValue = $lastVisits->get($customer->id);

            if (! $lastVisitValue) {
                $score += 20;
                $reasons[] = 'never_visited';
                $data['days_since_visit'] = null;
            } else {
                $lastVisit = CarbonImmutable::parse($lastVisitValue, 'UTC')
                    ->setTimezone($timezone);
                $daysSinceVisit = max(
                    0,
                    $lastVisit->startOfDay()->diffInDays($date->startOfDay()),
                );
                $data['days_since_visit'] = $daysSinceVisit;

                if ($daysSinceVisit >= 30) {
                    $score += 18;
                    $reasons[] = 'not_visited_30d';
                } elseif ($daysSinceVisit >= 14) {
                    $score += 10;
                    $reasons[] = 'not_visited_14d';
                } elseif ($daysSinceVisit >= 7) {
                    $score += 5;
                    $reasons[] = 'not_visited_7d';
                }
            }

            $score = min(100, $score);

            $result[$customer->id] = [
                'score' => $score,
                'tier' => match (true) {
                    $score >= 60 => 'urgent',
                    $score >= 35 => 'high',
                    $score >= 15 => 'normal',
                    default => 'routine',
                },
                'reason_codes' => array_values(array_unique($reasons)),
                'signals' => $data,
            ];
        }

        return $result;
    }

    private function overdueBalances(
        Customer $customer,
        Collection $orders,
        Collection $collections,
        CarbonImmutable $date,
        string $timezone,
    ): array {
        $result = [];

        foreach ($orders->groupBy('currency') as $currency => $currencyOrders) {
            $remainingCollections = (float) $collections->get(
                $customer->id.'|'.$currency,
                0,
            );
            $overdue = 0.0;

            foreach ($currencyOrders->sortBy('ordered_at') as $order) {
                $amount = (float) $order->grand_total;
                $applied = min($remainingCollections, $amount);
                $remainingCollections -= $applied;
                $outstanding = $amount - $applied;

                if ($outstanding <= 0) {
                    continue;
                }

                $dueDate = $order->due_date
                    ? CarbonImmutable::parse($order->due_date->toDateString(), $timezone)
                    : CarbonImmutable::parse($order->ordered_at, 'UTC')
                        ->setTimezone($timezone)
                        ->addDays((int) $customer->credit_terms_days);

                if ($dueDate->startOfDay()->lt($date->startOfDay())) {
                    $overdue += $outstanding;
                }
            }

            if ($overdue > 0) {
                $result[$currency] = round($overdue, 2);
            }
        }

        return $result;
    }

    private function sequence(Collection $candidates): Collection
    {
        $tierOrder = ['urgent', 'high', 'normal', 'routine'];
        $ordered = collect();
        $previous = null;

        foreach ($tierOrder as $tier) {
            $remaining = $candidates
                ->where('tier', $tier)
                ->values();

            while ($remaining->isNotEmpty()) {
                $next = $this->nextCandidate($remaining, $previous);
                $distance = $previous
                    ? $this->distanceMeters(
                        $previous['customer'],
                        $next['customer'],
                    )
                    : null;

                $next['distance_from_previous_m'] = $distance;
                $ordered->push($next);
                $previous = $next;

                $remaining = $remaining
                    ->reject(fn (array $candidate): bool => (
                        (int) $candidate['customer']->id
                        === (int) $next['customer']->id
                    ))
                    ->values();
            }
        }

        return $ordered;
    }

    private function nextCandidate(Collection $candidates, ?array $previous): array
    {
        if (! $previous || ! $this->hasCoordinates($previous['customer'])) {
            return $candidates
                ->sortBy(fn (array $candidate): array => [
                    -$candidate['score'],
                    $candidate['route_sequence'] ?? PHP_INT_MAX,
                    $candidate['customer']->name,
                ])
                ->first();
        }

        return $candidates
            ->sortBy(function (array $candidate) use ($previous): array {
                $distance = $this->distanceMeters(
                    $previous['customer'],
                    $candidate['customer'],
                );

                return [
                    $distance ?? PHP_INT_MAX,
                    -$candidate['score'],
                    $candidate['route_sequence'] ?? PHP_INT_MAX,
                    $candidate['customer']->name,
                ];
            })
            ->first();
    }

    private function completedVisits(
        Salesman $salesman,
        CarbonImmutable $date,
        string $timezone,
    ): Collection {
        $startUtc = $date->startOfDay()->utc();
        $endUtc = $date->endOfDay()->utc();

        return CustomerVisit::query()
            ->where('salesman_id', $salesman->id)
            ->where('status', 'completed')
            ->whereBetween('checked_in_at', [$startUtc, $endUtc])
            ->orderByDesc('checked_out_at')
            ->get()
            ->unique('customer_id')
            ->keyBy('customer_id');
    }

    private function distanceMeters(Customer $from, Customer $to): ?int
    {
        if (! $this->hasCoordinates($from) || ! $this->hasCoordinates($to)) {
            return null;
        }

        $earthRadius = 6371000;
        $lat1 = deg2rad((float) $from->latitude);
        $lat2 = deg2rad((float) $to->latitude);
        $deltaLat = deg2rad((float) $to->latitude - (float) $from->latitude);
        $deltaLon = deg2rad((float) $to->longitude - (float) $from->longitude);

        $a = sin($deltaLat / 2) ** 2
            + cos($lat1) * cos($lat2) * sin($deltaLon / 2) ** 2;

        return (int) round(
            $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a)),
        );
    }

    private function hasCoordinates(Customer $customer): bool
    {
        return $customer->latitude !== null
            && $customer->longitude !== null
            && ! (
                (float) $customer->latitude === 0.0
                && (float) $customer->longitude === 0.0
            );
    }
}
