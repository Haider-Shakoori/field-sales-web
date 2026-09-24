<?php

namespace App\Services;

use App\Models\LocationHistory;
use App\Models\Collection as PaymentCollection;
use App\Models\Expense;
use App\Models\Salesman;
use App\Models\SalesmanAssignment;
use App\Models\User;
use App\Models\VisitSuspiciousFlag;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class AlertService
{
    public function __construct(private readonly TenantClock $clock) {}

    public function build(User $actor, array $filters): array
    {
        [$start, $end, $fromDate, $toDate] = $this->window($actor, $filters);
        $salesmanIds = $this->visibleSalesmanIds($actor, $toDate);

        $flagQuery = VisitSuspiciousFlag::query()
            ->whereHas(
                'visit',
                fn ($visit) => $visit->whereIn('salesman_id', $salesmanIds),
            )
            ->where('created_at', '>=', $start)
            ->where('created_at', '<', $end)
            ->when(
                $filters['severity'] ?? null,
                fn ($query, $severity) => $query->where('severity', $severity),
            )
            ->when(
                ($filters['state'] ?? null) === 'open',
                fn ($query) => $query->whereNull('reviewed_at'),
            )
            ->when(
                ($filters['state'] ?? null) === 'reviewed',
                fn ($query) => $query->whereNotNull('reviewed_at'),
            );

        $openFlagCount = (clone $flagQuery)->whereNull('reviewed_at')->count();
        $reviewedFlagCount = (clone $flagQuery)->whereNotNull('reviewed_at')->count();

        $flags = (clone $flagQuery)
            ->with([
                'visit.customer',
                'visit.salesman.user',
                'reviewer',
            ])
            ->latest()
            ->limit(100)
            ->get();

        $mockQuery = LocationHistory::query()
            ->whereIn('salesman_id', $salesmanIds)
            ->where('is_mock_location', true)
            ->where('recorded_at', '>=', $start)
            ->where('recorded_at', '<', $end);

        $mockPointCount = (clone $mockQuery)->count();
        $mockPoints = (clone $mockQuery)
            ->latest('recorded_at')
            ->limit(100)
            ->get();

        $salesmen = Salesman::query()
            ->whereIn('id', $mockPoints->pluck('salesman_id')->unique())
            ->get()
            ->keyBy('id');

        $collections = PaymentCollection::query()->with(['customer','salesman'])->whereIn('salesman_id', $salesmanIds)->where('collected_at','>=',$start)->where('collected_at','<',$end)->orderBy('collected_at')->get();
        $expenses = Expense::query()->with('salesman')->whereIn('salesman_id', $salesmanIds)->where('spent_at','>=',$start)->where('spent_at','<',$end)->orderBy('spent_at')->get();
        $signals = collect();
        foreach ($collections->where('within_geofence', false) as $row) $signals->push(['type'=>'collection_outside_geofence','severity'=>'high','salesman'=>$row->salesman?->full_name,'customer'=>$row->customer?->name,'occurred_at'=>$row->collected_at?->toISOString(),'detail'=>'Collection was recorded outside the customer geofence.']);
        foreach ($collections->where('overpayment_flag', true) as $row) $signals->push(['type'=>'collection_overpayment','severity'=>'medium','salesman'=>$row->salesman?->full_name,'customer'=>$row->customer?->name,'occurred_at'=>$row->collected_at?->toISOString(),'detail'=>'Collection exceeded the balance snapshot captured at entry.']);
        foreach ($collections->groupBy(fn($row)=>$row->salesman_id.':'.$row->customer_id.':'.$row->currency.':'.$row->amount) as $group) { $prev=null; foreach($group as $row){ if($prev && $prev->collected_at->diffInMinutes($row->collected_at)<=10) $signals->push(['type'=>'duplicate_collection_window','severity'=>'high','salesman'=>$row->salesman?->full_name,'customer'=>$row->customer?->name,'occurred_at'=>$row->collected_at?->toISOString(),'detail'=>'Same customer, amount and currency were collected again within 10 minutes.']); $prev=$row; } }
        foreach ($expenses->groupBy(fn($row)=>$row->salesman_id.':'.$row->category.':'.$row->currency.':'.$row->amount) as $group) { $prev=null; foreach($group as $row){ if($prev && $prev->spent_at->diffInMinutes($row->spent_at)<=10) $signals->push(['type'=>'duplicate_expense_window','severity'=>'medium','salesman'=>$row->salesman?->full_name,'customer'=>null,'occurred_at'=>$row->spent_at?->toISOString(),'detail'=>'Same expense category, amount and currency were submitted again within 10 minutes.']); $prev=$row; } }

        return [
            'period' => [$fromDate, $toDate],
            'flags' => $flags,
            'mock_points' => $mockPoints,
            'mock_salesmen' => $salesmen,
            'summary' => [
                'open_visit_flags' => $openFlagCount,
                'reviewed_visit_flags' => $reviewedFlagCount,
                'mock_location_points' => $mockPointCount,
                'derived_anomaly_signals' => $signals->count(),
            ],
            'derived_signals' => $signals->sortByDesc('occurred_at')->take(150)->values(),
        ];
    }

    private function visibleSalesmanIds(User $actor, string $localDate): Collection
    {
        $actor->loadMissing('supervisor');
        $query = Salesman::active();

        if ($actor->hasAnyRole(['supervisor'])) {
            if (! $actor->supervisor) {
                return collect();
            }

            $query->whereIn(
                'id',
                SalesmanAssignment::query()
                    ->where('supervisor_id', $actor->supervisor->id)
                    ->current($localDate)
                    ->pluck('salesman_id'),
            );
        }

        return $query->pluck('id');
    }

    private function window(User $actor, array $filters): array
    {
        $timezone = $this->clock->timezone($actor->tenant);
        $today = $this->clock->now($actor->tenant)->toDateString();
        $fromDate = $filters['date_from'] ?? CarbonImmutable::parse($today, $timezone)
            ->subDays(6)
            ->toDateString();
        $toDate = $filters['date_to'] ?? $today;

        return [
            CarbonImmutable::parse($fromDate.' 00:00:00', $timezone)->utc(),
            CarbonImmutable::parse($toDate.' 00:00:00', $timezone)->addDay()->utc(),
            $fromDate,
            $toDate,
        ];
    }
}
