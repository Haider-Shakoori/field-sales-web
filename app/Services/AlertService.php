<?php

namespace App\Services;

use App\Models\LocationHistory;
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

        $flags = VisitSuspiciousFlag::with([
            'visit.customer',
            'visit.salesman.user',
            'reviewer',
        ])
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
            )
            ->latest()
            ->limit(100)
            ->get();

        $mockPoints = LocationHistory::query()
            ->whereIn('salesman_id', $salesmanIds)
            ->where('is_mock_location', true)
            ->where('recorded_at', '>=', $start)
            ->where('recorded_at', '<', $end)
            ->latest('recorded_at')
            ->limit(100)
            ->get();

        $salesmen = Salesman::query()
            ->whereIn('id', $mockPoints->pluck('salesman_id')->unique())
            ->get()
            ->keyBy('id');

        return [
            'period' => [$fromDate, $toDate],
            'flags' => $flags,
            'mock_points' => $mockPoints,
            'mock_salesmen' => $salesmen,
            'summary' => [
                'open_visit_flags' => $flags->whereNull('reviewed_at')->count(),
                'reviewed_visit_flags' => $flags->whereNotNull('reviewed_at')->count(),
                'mock_location_points' => $mockPoints->count(),
            ],
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
