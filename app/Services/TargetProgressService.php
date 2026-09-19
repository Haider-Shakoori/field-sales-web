<?php

namespace App\Services;

use App\Models\Collection;
use App\Models\CustomerVisit;
use App\Models\Order;
use App\Models\SalesTarget;
use Carbon\CarbonImmutable;

class TargetProgressService
{
    public function payload(SalesTarget $target): array
    {
        $target->loadMissing(['salesman.user', 'tenant']);

        $timezone = $target->tenant->timezone ?: 'UTC';
        $start = CarbonImmutable::parse($target->period_start->toDateString(), $timezone)
            ->startOfDay()
            ->utc();
        $end = CarbonImmutable::parse($target->period_end->toDateString(), $timezone)
            ->endOfDay()
            ->utc();

        $achieved = match ($target->target_type) {
            'sales_amount' => (float) Order::query()
                ->where('salesman_id', $target->salesman_id)
                ->where('status', 'approved')
                ->where('currency', $target->currency)
                ->whereBetween('ordered_at', [$start, $end])
                ->sum('grand_total'),
            'collections_amount' => (float) Collection::query()
                ->where('salesman_id', $target->salesman_id)
                ->where('status', 'verified')
                ->where('currency', $target->currency)
                ->whereBetween('collected_at', [$start, $end])
                ->sum('amount'),
            'orders_count' => (float) Order::query()
                ->where('salesman_id', $target->salesman_id)
                ->where('status', 'approved')
                ->whereBetween('ordered_at', [$start, $end])
                ->count(),
            'visits_count' => (float) CustomerVisit::query()
                ->where('salesman_id', $target->salesman_id)
                ->where('status', 'completed')
                ->whereBetween('checked_in_at', [$start, $end])
                ->count(),
            default => 0.0,
        };

        $goal = (float) $target->target_value;
        $remaining = max(0, round($goal - $achieved, 4));
        $percentage = $goal > 0 ? round(($achieved / $goal) * 100, 2) : 0.0;

        return [
            'id' => $target->uuid,
            'salesman_id' => $target->salesman?->uuid,
            'salesman_name' => $target->salesman?->full_name,
            'target_type' => $target->target_type,
            'currency' => $target->currency,
            'target_value' => $goal,
            'achieved_value' => round($achieved, 4),
            'remaining_value' => $remaining,
            'progress_percent' => $percentage,
            'period_start' => $target->period_start?->toDateString(),
            'period_end' => $target->period_end?->toDateString(),
            'notes' => $target->notes,
        ];
    }
}
