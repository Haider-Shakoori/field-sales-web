<?php

namespace App\Services;

use App\Models\Collection;
use App\Models\Customer;
use App\Models\CustomerVisit;
use App\Models\Order;
use App\Models\SalesReturn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection as SupportCollection;

class Customer360Service
{
    public function build(Customer $customer): array
    {
        $visits = CustomerVisit::query()
            ->where('customer_id', $customer->id)
            ->with('salesman')
            ->latest('checked_in_at');

        $orders = Order::query()
            ->where('customer_id', $customer->id)
            ->with('salesman')
            ->latest('ordered_at');

        $collections = Collection::query()
            ->where('customer_id', $customer->id)
            ->with('salesman')
            ->latest('collected_at');

        $returns = SalesReturn::query()
            ->where('customer_id', $customer->id)
            ->with('salesman')
            ->latest('returned_at');

        $recentActivity = collect()
            ->concat(
                (clone $visits)->limit(8)->get()->map(fn (CustomerVisit $visit) => [
                    'type' => 'visit',
                    'occurred_at' => $visit->checked_in_at,
                    'title' => 'Visit',
                    'reference' => $visit->outcome
                        ? str($visit->outcome)->replace('_', ' ')->title()->toString()
                        : str($visit->status)->title()->toString(),
                    'status' => $visit->status,
                    'salesman' => $visit->salesman?->full_name,
                    'amount' => null,
                    'currency' => null,
                ])
            )
            ->concat(
                (clone $orders)->limit(8)->get()->map(fn (Order $order) => [
                    'type' => 'order',
                    'occurred_at' => $order->ordered_at,
                    'title' => 'Order',
                    'reference' => $order->order_number,
                    'status' => $order->status,
                    'salesman' => $order->salesman?->full_name,
                    'amount' => (float) $order->grand_total,
                    'currency' => $order->currency,
                ])
            )
            ->concat(
                (clone $collections)->limit(8)->get()->map(fn (Collection $collection) => [
                    'type' => 'collection',
                    'occurred_at' => $collection->collected_at,
                    'title' => 'Collection',
                    'reference' => $collection->receipt_number,
                    'status' => $collection->status,
                    'salesman' => $collection->salesman?->full_name,
                    'amount' => (float) $collection->amount,
                    'currency' => $collection->currency,
                ])
            )
            ->concat(
                (clone $returns)->limit(8)->get()->map(fn (SalesReturn $return) => [
                    'type' => 'return',
                    'occurred_at' => $return->returned_at,
                    'title' => 'Return',
                    'reference' => $return->return_number,
                    'status' => $return->status,
                    'salesman' => $return->salesman?->full_name,
                    'amount' => null,
                    'currency' => null,
                ])
            )
            ->sortByDesc('occurred_at')
            ->take(20)
            ->values();

        return [
            'summary' => [
                'visits_count' => (clone $visits)->count(),
                'completed_visits_count' => (clone $visits)->where('status', 'completed')->count(),
                'orders_count' => (clone $orders)->count(),
                'approved_orders_count' => (clone $orders)->where('status', 'approved')->count(),
                'collections_count' => (clone $collections)->count(),
                'verified_collections_count' => (clone $collections)->where('status', 'verified')->count(),
                'returns_count' => (clone $returns)->count(),
                'last_visit_at' => (clone $visits)->value('checked_in_at'),
                'last_order_at' => (clone $orders)->value('ordered_at'),
                'last_collection_at' => (clone $collections)->value('collected_at'),
            ],
            'approved_order_totals' => $this->moneyTotals(
                (clone $orders)->where('status', 'approved'),
                'grand_total',
            ),
            'verified_collection_totals' => $this->moneyTotals(
                (clone $collections)->where('status', 'verified'),
                'amount',
            ),
            'recent_activity' => $recentActivity,
        ];
    }

    private function moneyTotals(Builder $query, string $column): SupportCollection
    {
        return $query
            ->reorder()
            ->selectRaw('currency, SUM('.$column.') AS total')
            ->groupBy('currency')
            ->orderBy('currency')
            ->get()
            ->map(fn ($row) => [
                'currency' => $row->currency,
                'total' => round((float) $row->total, 2),
            ]);
    }
}
