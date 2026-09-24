<?php

namespace App\Services;

use App\Models\Collection;
use App\Models\CustomerFollowUp;
use App\Models\CustomerVisit;
use App\Models\Expense;
use App\Models\Order;
use App\Models\Salesman;
use App\Models\SalesReturn;
use App\Models\User;
use App\Models\VisitSuspiciousFlag;
use App\Models\WorkSession;
use Carbon\CarbonImmutable;

class ManagerBriefingService
{
    public function __construct(
        private readonly AiRecommendationService $recommendations,
        private readonly TenantClock $clock,
    ) {}

    public function build(User $user): array
    {
        $user->loadMissing('tenant');
        $timezone = $this->clock->timezone($user->tenant);
        $now = CarbonImmutable::now($timezone);
        $today = $now->toDateString();
        $yesterday = $now->subDay();
        $yesterdayStart = $yesterday->startOfDay()->utc();
        $yesterdayEnd = $yesterday->endOfDay()->utc();

        $sales = Order::query()
            ->where('status', 'approved')
            ->whereBetween('ordered_at', [$yesterdayStart, $yesterdayEnd])
            ->selectRaw('currency, COUNT(*) as count, SUM(grand_total) as total')
            ->groupBy('currency')
            ->get()
            ->map(fn ($row) => [
                'currency' => $row->currency,
                'count' => (int) $row->count,
                'total' => round((float) $row->total, 4),
            ])
            ->values()
            ->all();

        $collections = Collection::query()
            ->where('status', 'verified')
            ->whereBetween('collected_at', [$yesterdayStart, $yesterdayEnd])
            ->selectRaw('currency, COUNT(*) as count, SUM(amount) as total')
            ->groupBy('currency')
            ->get()
            ->map(fn ($row) => [
                'currency' => $row->currency,
                'count' => (int) $row->count,
                'total' => round((float) $row->total, 4),
            ])
            ->values()
            ->all();

        $activeSalesmen = Salesman::active()->count();
        $startedToday = WorkSession::query()
            ->whereDate('date', $today)
            ->distinct('salesman_id')
            ->count('salesman_id');

        $recommendations = $this->recommendations->build($user, 8);

        return [
            'generated_at' => $now->toIso8601String(),
            'generated_time' => $now->format('H:i'),
            'timezone' => $timezone,
            'today' => $today,
            'yesterday' => $yesterday->toDateString(),
            'yesterday_sales' => $sales,
            'yesterday_collections' => $collections,
            'yesterday_visits' => CustomerVisit::query()
                ->whereBetween('checked_in_at', [$yesterdayStart, $yesterdayEnd])
                ->count(),
            'today_attendance' => [
                'active_salesmen' => $activeSalesmen,
                'started' => $startedToday,
                'not_started' => max(0, $activeSalesmen - $startedToday),
            ],
            'pending' => [
                'orders' => $user->hasPermission('orders:view')
                    ? Order::query()->where('status', 'pending')->count()
                    : null,
                'collections' => $user->hasPermission('collections:view')
                    ? Collection::query()->where('status', 'pending')->count()
                    : null,
                'expenses' => $user->hasPermission('expenses:view')
                    ? Expense::query()->where('status', 'pending')->count()
                    : null,
                'returns' => $user->hasPermission('returns:view')
                    ? SalesReturn::query()->where('status', 'pending')->count()
                    : null,
            ],
            'exceptions' => [
                'overdue_followups' => $user->hasPermission('customers:view')
                    ? CustomerFollowUp::query()
                        ->where('status', 'pending')
                        ->where('due_at', '<', now())
                        ->count()
                    : null,
                'unreviewed_visit_flags' => $user->hasPermission('visits:view')
                    ? VisitSuspiciousFlag::query()
                        ->whereNull('reviewed_at')
                        ->count()
                    : null,
            ],
            'priorities' => $recommendations,
        ];
    }
}
