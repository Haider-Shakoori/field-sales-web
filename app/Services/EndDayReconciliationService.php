<?php

namespace App\Services;

use App\Models\Collection;
use App\Models\CustomerVisit;
use App\Models\Expense;
use App\Models\Order;
use App\Models\SalesReturn;
use App\Models\User;
use App\Models\WorkSession;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

class EndDayReconciliationService
{
    public function __construct(
        private readonly DailyRoutePlannerService $planner,
    ) {}

    public function build(User $user): array
    {
        $user->loadMissing(['tenant', 'salesman']);

        abort_unless($user->salesman?->is_active, 403);

        $timezone = $user->tenant?->timezone ?: config('app.timezone', 'UTC');
        $localDate = CarbonImmutable::now($timezone)->startOfDay();
        $start = $localDate->utc();
        $end = $localDate->addDay()->utc();
        $salesman = $user->salesman;

        $plan = $this->planner->planFor($salesman, $localDate);
        $session = WorkSession::query()
            ->where('user_id', $user->id)
            ->whereDate('date', $localDate->toDateString())
            ->latest('start_time')
            ->first();

        $visits = CustomerVisit::query()
            ->where('salesman_id', $salesman->id)
            ->where('checked_in_at', '>=', $start)
            ->where('checked_in_at', '<', $end);

        $orders = Order::query()
            ->where('salesman_id', $salesman->id)
            ->where('ordered_at', '>=', $start)
            ->where('ordered_at', '<', $end);

        $collections = Collection::query()
            ->where('salesman_id', $salesman->id)
            ->where('collected_at', '>=', $start)
            ->where('collected_at', '<', $end);

        $expenses = Expense::query()
            ->where('salesman_id', $salesman->id)
            ->where('spent_at', '>=', $start)
            ->where('spent_at', '<', $end);

        $returns = SalesReturn::query()
            ->where('salesman_id', $salesman->id)
            ->where('returned_at', '>=', $start)
            ->where('returned_at', '<', $end);

        $missed = collect($plan['stops'] ?? [])
            ->where('visited_today', false)
            ->map(fn (array $stop) => [
                'customer_id' => $stop['customer_id'],
                'customer_code' => $stop['customer_code'],
                'customer_name' => $stop['customer_name'],
                'priority' => $stop['priority'],
                'is_opportunity' => (bool) ($stop['is_opportunity'] ?? false),
            ])
            ->values()
            ->all();

        return [
            'date' => $localDate->toDateString(),
            'timezone' => $timezone,
            'generated_at' => now()->toISOString(),
            'plan' => [
                'source' => $plan['source'] ?? null,
                'route' => $plan['route'] ?? null,
                'summary' => $plan['summary'] ?? [],
                'missed_customers' => $missed,
            ],
            'visits' => [
                'started' => (clone $visits)->count(),
                'completed' => (clone $visits)->where('status', 'completed')->count(),
                'active' => (clone $visits)->where('status', 'active')->count(),
            ],
            'orders' => [
                'total_count' => (clone $orders)->count(),
                'approved_count' => (clone $orders)->where('status', 'approved')->count(),
                'pending_count' => (clone $orders)->where('status', 'pending')->count(),
                'approved_totals' => $this->moneyTotals(
                    (clone $orders)->where('status', 'approved'),
                    'grand_total',
                ),
                'all_totals' => $this->moneyTotals(
                    clone $orders,
                    'grand_total',
                ),
            ],
            'collections' => [
                'total_count' => (clone $collections)->count(),
                'verified_count' => (clone $collections)->where('status', 'verified')->count(),
                'pending_count' => (clone $collections)->where('status', 'pending')->count(),
                'verified_totals' => $this->moneyTotals(
                    (clone $collections)->where('status', 'verified'),
                    'amount',
                ),
                'all_totals' => $this->moneyTotals(
                    clone $collections,
                    'amount',
                ),
            ],
            'expenses' => [
                'total_count' => (clone $expenses)->count(),
                'approved_count' => (clone $expenses)->where('status', 'approved')->count(),
                'pending_count' => (clone $expenses)->where('status', 'pending')->count(),
                'approved_totals' => $this->moneyTotals(
                    (clone $expenses)->where('status', 'approved'),
                    'amount',
                ),
                'all_totals' => $this->moneyTotals(
                    clone $expenses,
                    'amount',
                ),
            ],
            'returns' => [
                'total_count' => (clone $returns)->count(),
                'approved_count' => (clone $returns)->where('status', 'approved')->count(),
                'pending_count' => (clone $returns)->where('status', 'pending')->count(),
                'rejected_count' => (clone $returns)->where('status', 'rejected')->count(),
            ],
            'mileage' => [
                'vehicle_reference' => $session?->vehicle_reference,
                'odometer_start_km' => $session?->odometer_start_km === null
                    ? null
                    : (float) $session->odometer_start_km,
                'odometer_end_km' => $session?->odometer_end_km === null
                    ? null
                    : (float) $session->odometer_end_km,
                'gps_distance_km' => $session?->gps_distance_km === null
                    ? null
                    : (float) $session->gps_distance_km,
            ],
            'session' => [
                'status' => $session?->status,
                'started_at' => $session?->start_time?->toISOString(),
                'ended_at' => $session?->end_time?->toISOString(),
                'notes' => $session?->notes,
            ],
        ];
    }

    private function moneyTotals(Builder $query, string $column): array
    {
        return $query
            ->selectRaw('currency, SUM('.$column.') AS total')
            ->groupBy('currency')
            ->orderBy('currency')
            ->get()
            ->map(fn ($row) => [
                'currency' => $row->currency,
                'total' => round((float) $row->total, 2),
            ])
            ->values()
            ->all();
    }
}
