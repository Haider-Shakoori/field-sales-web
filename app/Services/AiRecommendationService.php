<?php

namespace App\Services;

use App\Models\Collection;
use App\Models\Customer;
use App\Models\CustomerVisit;
use App\Models\Expense;
use App\Models\Order;
use App\Models\SalesReturn;
use App\Models\Salesman;
use App\Models\User;
use App\Models\VisitSuspiciousFlag;
use App\Models\WorkSession;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection as SupportCollection;

class AiRecommendationService
{
    public function __construct(
        private readonly CustomerBalanceService $balances,
        private readonly TenantClock $clock,
    ) {}

    public function build(User $user, int $limit = 12): array
    {
        $user->loadMissing('tenant');
        $timezone = $this->clock->timezone($user->tenant);
        $now = CarbonImmutable::now($timezone);
        $recommendations = collect();

        $this->approvalRecommendations($user, $recommendations);
        $this->attendanceRecommendations($user, $recommendations, $now);
        $this->visitRiskRecommendations($user, $recommendations, $now);
        $this->customerRecommendations($user, $recommendations, $now);

        return $recommendations
            ->sortByDesc('score')
            ->take(max(1, min(50, $limit)))
            ->values()
            ->all();
    }

    private function approvalRecommendations(
        User $user,
        SupportCollection $items,
    ): void {
        if ($user->hasPermission('orders:view')) {
            $pendingOrders = Order::query()
                ->where('status', 'pending')
                ->count();

            if ($pendingOrders > 0) {
                $items->push($this->item(
                    'pending_orders',
                    'approvals',
                    'high',
                    78,
                    'Review pending orders',
                    ':count order(s) are waiting for management review.',
                    ['count' => $pendingOrders],
                    'Open Orders and clear pending approvals.',
                    [
                        'pending_orders' => $pendingOrders,
                    ],
                ));
            }
        }

        if ($user->hasPermission('collections:view')) {
            $pendingCollections = Collection::query()
                ->where('status', 'pending')
                ->count();

            if ($pendingCollections > 0) {
                $items->push($this->item(
                    'pending_collections',
                    'collections',
                    'high',
                    82,
                    'Verify pending collections',
                    ':count collection(s) are awaiting verification.',
                    ['count' => $pendingCollections],
                    'Verify payment evidence and reconcile balances.',
                    [
                        'pending_collections' => $pendingCollections,
                    ],
                ));
            }
        }

        if ($user->hasPermission('expenses:view')) {
            $pendingExpenses = Expense::query()
                ->where('status', 'pending')
                ->count();

            if ($pendingExpenses > 0) {
                $items->push($this->item(
                    'pending_expenses',
                    'expenses',
                    'medium',
                    58,
                    'Review pending expenses',
                    ':count expense(s) are waiting for approval.',
                    ['count' => $pendingExpenses],
                    'Review supporting details before approving or rejecting expenses.',
                    [
                        'pending_expenses' => $pendingExpenses,
                    ],
                ));
            }
        }

        if ($user->hasPermission('returns:view')) {
            $pendingReturns = SalesReturn::query()
                ->where('status', 'pending')
                ->count();

            if ($pendingReturns > 0) {
                $items->push($this->item(
                    'pending_returns',
                    'returns',
                    'medium',
                    62,
                    'Review pending returns',
                    ':count customer return(s) are waiting for review.',
                    ['count' => $pendingReturns],
                    'Review return condition and stock impact.',
                    [
                        'pending_returns' => $pendingReturns,
                    ],
                ));
            }
        }
    }

    private function attendanceRecommendations(
        User $user,
        SupportCollection $items,
        CarbonImmutable $now,
    ): void {
        if (! $user->hasPermission('sales-team:view')) {
            return;
        }

        $activeSalesmen = Salesman::active()->count();
        $started = WorkSession::query()
            ->whereDate('date', $now->toDateString())
            ->distinct('salesman_id')
            ->count('salesman_id');

        $notStarted = max(0, $activeSalesmen - $started);

        if ($notStarted > 0) {
            $items->push($this->item(
                'attendance_not_started',
                'attendance',
                'high',
                74,
                'Check field attendance',
                ':count active salesman/salesmen have not started work today.',
                ['count' => $notStarted],
                'Confirm leave, delayed Start Day, or device issues.',
                [
                    'active_salesmen' => $activeSalesmen,
                    'started_today' => $started,
                    'not_started_today' => $notStarted,
                ],
            ));
        }
    }

    private function visitRiskRecommendations(
        User $user,
        SupportCollection $items,
        CarbonImmutable $now,
    ): void {
        if ($user->hasPermission('visits:view')) {
            $unreviewedFlags = VisitSuspiciousFlag::query()
                ->whereNull('reviewed_at')
                ->count();

            if ($unreviewedFlags > 0) {
                $items->push($this->item(
                    'unreviewed_visit_flags',
                    'risk',
                    'critical',
                    96,
                    'Review suspicious visit activity',
                    ':count suspicious visit flag(s) still require review.',
                    ['count' => $unreviewedFlags],
                    'Review flagged visits before relying on affected field activity.',
                    [
                        'unreviewed_flags' => $unreviewedFlags,
                    ],
                ));
            }
        }

        if (! $user->hasPermission('customers:view')) {
            return;
        }

        $cutoff = $now->subDays(30)->utc();
        $staleCustomers = Customer::active()
            ->whereDoesntHave(
                'visits',
                fn ($query) => $query->where('checked_in_at', '>=', $cutoff),
            )
            ->count();

        if ($staleCustomers > 0) {
            $items->push($this->item(
                'stale_customer_coverage',
                'coverage',
                'high',
                80,
                'Recover stale customer coverage',
                ':count active customer(s) have not been visited in the last 30 days.',
                ['count' => $staleCustomers],
                'Prioritize stale accounts in Smart Route / Daily Planner.',
                [
                    'stale_customer_count' => $staleCustomers,
                    'days_without_visit' => 30,
                ],
            ));
        }
    }

    private function customerRecommendations(
        User $user,
        SupportCollection $items,
        CarbonImmutable $now,
    ): void {
        if (! $user->hasPermission('customers:view')) {
            return;
        }

        $customers = Customer::active()
            ->withMax('visits', 'checked_in_at')
            ->get();

        if ($customers->isEmpty()) {
            return;
        }

        $this->receivableRecommendations($customers, $items);
        $this->decliningSalesRecommendations($customers, $items, $now);
        $this->reorderRecommendations($customers, $items, $now);
    }

    private function receivableRecommendations(
        SupportCollection $customers,
        SupportCollection $items,
    ): void {
        $balanceRows = collect($this->balances->forCustomers($customers))
            ->flatMap(function (array $row): array {
                return collect($row['balances'])
                    ->filter(
                        fn (array $balance) => (float) $balance['outstanding_balance'] > 0
                    )
                    ->map(fn (array $balance) => [
                        'customer_id' => $row['customer_id'],
                        'customer_name' => $row['customer_name'],
                        ...$balance,
                    ])
                    ->all();
            })
            ->sortByDesc('outstanding_balance')
            ->take(10);

        foreach ($balanceRows as $row) {
            $customer = $customers->firstWhere('uuid', $row['customer_id']);
            if (! $customer) {
                continue;
            }

            $aging = collect($this->balances->aging($customer))
                ->firstWhere('currency', $row['currency']);
            $overdue = (float) ($aging['overdue_total'] ?? 0);

            if ($overdue <= 0) {
                continue;
            }

            $days90 = (float) ($aging['days_90_plus'] ?? 0);
            $severity = $days90 > 0 ? 'critical' : 'high';
            $score = $days90 > 0 ? 100 : 90;

            $items->push($this->item(
                'overdue_receivable_'.$customer->uuid.'_'.$row['currency'],
                'receivables',
                $severity,
                $score,
                'Prioritize overdue receivable',
                ':customer has :amount :currency overdue.',
                [
                    'customer' => $customer->name,
                    'amount' => number_format($overdue, 2),
                    'currency' => $row['currency'],
                ],
                'Review the account aging and schedule a collection follow-up.',
                [
                    'customer_uuid' => $customer->uuid,
                    'customer' => $customer->name,
                    'currency' => $row['currency'],
                    'overdue_total' => round($overdue, 4),
                    'days_90_plus' => round($days90, 4),
                    'outstanding_balance' => round(
                        (float) $row['outstanding_balance'],
                        4,
                    ),
                ],
            ));
        }
    }

    private function decliningSalesRecommendations(
        SupportCollection $customers,
        SupportCollection $items,
        CarbonImmutable $now,
    ): void {
        $customerIds = $customers->pluck('id')->all();
        $currentStart = $now->subDays(29)->startOfDay()->utc();
        $currentEnd = $now->endOfDay()->utc();
        $previousStart = $now->subDays(59)->startOfDay()->utc();
        $previousEnd = $now->subDays(30)->endOfDay()->utc();

        $current = $this->salesTotals(
            $customerIds,
            $currentStart,
            $currentEnd,
        );
        $previous = $this->salesTotals(
            $customerIds,
            $previousStart,
            $previousEnd,
        );

        foreach ($previous as $key => $previousTotal) {
            if ($previousTotal <= 0) {
                continue;
            }

            $currentTotal = (float) ($current[$key] ?? 0);
            $declinePercent = (($previousTotal - $currentTotal) / $previousTotal) * 100;

            if ($declinePercent < 30) {
                continue;
            }

            [$customerId, $currency] = explode('|', $key, 2);
            $customer = $customers->firstWhere('id', (int) $customerId);
            if (! $customer) {
                continue;
            }

            $items->push($this->item(
                'declining_sales_'.$customer->uuid.'_'.$currency,
                'sales',
                $declinePercent >= 60 ? 'high' : 'medium',
                $declinePercent >= 60 ? 84 : 68,
                'Investigate declining customer sales',
                ':customer sales are down :percent% versus the previous 30-day period.',
                [
                    'customer' => $customer->name,
                    'percent' => number_format($declinePercent, 0),
                ],
                'Review recent visits, stock needs, pricing, and competitor activity.',
                [
                    'customer_uuid' => $customer->uuid,
                    'customer' => $customer->name,
                    'currency' => $currency,
                    'current_30d_sales' => round($currentTotal, 4),
                    'previous_30d_sales' => round($previousTotal, 4),
                    'decline_percent' => round($declinePercent, 2),
                ],
            ));
        }
    }

    private function reorderRecommendations(
        SupportCollection $customers,
        SupportCollection $items,
        CarbonImmutable $now,
    ): void {
        $rows = Order::query()
            ->whereIn('customer_id', $customers->pluck('id'))
            ->where('status', 'approved')
            ->where('ordered_at', '>=', $now->subDays(180)->utc())
            ->orderBy('customer_id')
            ->orderBy('ordered_at')
            ->get(['customer_id', 'ordered_at'])
            ->groupBy('customer_id');

        foreach ($rows as $customerId => $orders) {
            if ($orders->count() < 3) {
                continue;
            }

            $dates = $orders
                ->pluck('ordered_at')
                ->filter()
                ->map(fn ($date) => $date->toImmutable())
                ->values();

            if ($dates->count() < 3) {
                continue;
            }

            $intervals = [];
            for ($index = 1; $index < $dates->count(); $index++) {
                $intervals[] = max(
                    1,
                    (int) $dates[$index - 1]->diffInDays($dates[$index]),
                );
            }

            $averageInterval = (int) round(collect($intervals)->avg());
            $lastOrder = $dates->last();
            $daysSinceLast = (int) $lastOrder->diffInDays($now->utc());

            if ($daysSinceLast < max(7, $averageInterval)) {
                continue;
            }

            $customer = $customers->firstWhere('id', (int) $customerId);
            if (! $customer) {
                continue;
            }

            $items->push($this->item(
                'reorder_due_'.$customer->uuid,
                'reorder',
                $daysSinceLast >= ($averageInterval * 1.5) ? 'high' : 'medium',
                $daysSinceLast >= ($averageInterval * 1.5) ? 79 : 66,
                'Customer may be due for reorder',
                ':customer usually orders every :interval days and the last approved order was :days days ago.',
                [
                    'customer' => $customer->name,
                    'interval' => $averageInterval,
                    'days' => $daysSinceLast,
                ],
                'Review the customer purchase history and schedule a visit or call.',
                [
                    'customer_uuid' => $customer->uuid,
                    'customer' => $customer->name,
                    'average_order_interval_days' => $averageInterval,
                    'days_since_last_order' => $daysSinceLast,
                    'orders_analyzed' => $dates->count(),
                ],
            ));
        }
    }

    private function salesTotals(
        array $customerIds,
        CarbonImmutable $start,
        CarbonImmutable $end,
    ): array {
        return Order::query()
            ->whereIn('customer_id', $customerIds)
            ->where('status', 'approved')
            ->whereBetween('ordered_at', [$start, $end])
            ->selectRaw(
                'customer_id, currency, SUM(grand_total) as total'
            )
            ->groupBy('customer_id', 'currency')
            ->get()
            ->mapWithKeys(fn ($row) => [
                $row->customer_id.'|'.$row->currency => (float) $row->total,
            ])
            ->all();
    }

    private function item(
        string $id,
        string $category,
        string $severity,
        int $score,
        string $title,
        string $message,
        array $messageParams,
        string $action,
        array $evidence,
    ): array {
        return [
            'id' => $id,
            'category' => $category,
            'severity' => $severity,
            'score' => $score,
            'title' => $title,
            'message' => $message,
            'message_params' => $messageParams,
            'action' => $action,
            'evidence' => $evidence,
        ];
    }
}
