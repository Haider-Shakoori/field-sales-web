<?php

namespace App\Services;

use App\Models\Collection;
use App\Models\Customer;
use App\Models\Order;
use Carbon\CarbonImmutable;

class CustomerStatementService
{
    public function build(
        Customer $customer,
        string $currency,
        CarbonImmutable $fromUtc,
        CarbonImmutable $toUtc,
    ): array {
        $currency = strtoupper($currency);

        $openingDebits = (float) Order::query()
            ->where('customer_id', $customer->id)
            ->where('payment_type', 'credit')
            ->where('status', 'approved')
            ->where('currency', $currency)
            ->where('ordered_at', '<', $fromUtc)
            ->sum('grand_total');

        $openingCredits = (float) Collection::query()
            ->where('customer_id', $customer->id)
            ->where('status', 'verified')
            ->where('currency', $currency)
            ->where('collected_at', '<', $fromUtc)
            ->sum('amount');

        $opening = round($openingDebits - $openingCredits, 4);

        $orders = Order::query()
            ->where('customer_id', $customer->id)
            ->where('payment_type', 'credit')
            ->where('status', 'approved')
            ->where('currency', $currency)
            ->whereBetween('ordered_at', [$fromUtc, $toUtc])
            ->get(['id', 'order_number', 'ordered_at', 'grand_total']);

        $collections = Collection::query()
            ->where('customer_id', $customer->id)
            ->where('status', 'verified')
            ->where('currency', $currency)
            ->whereBetween('collected_at', [$fromUtc, $toUtc])
            ->get(['id', 'receipt_number', 'collected_at', 'amount']);

        $entries = [];

        foreach ($orders as $order) {
            $entries[] = [
                'type' => 'invoice',
                'sort_type' => 0,
                'source_id' => $order->id,
                'occurred_at' => $order->ordered_at->toImmutable(),
                'reference' => $order->order_number,
                'description' => 'Credit sale',
                'debit' => round((float) $order->grand_total, 4),
                'credit' => 0.0,
            ];
        }

        foreach ($collections as $collection) {
            $entries[] = [
                'type' => 'collection',
                'sort_type' => 1,
                'source_id' => $collection->id,
                'occurred_at' => $collection->collected_at->toImmutable(),
                'reference' => $collection->receipt_number,
                'description' => 'Payment received',
                'debit' => 0.0,
                'credit' => round((float) $collection->amount, 4),
            ];
        }

        usort($entries, static function (array $left, array $right): int {
            $time = $left['occurred_at']->getTimestamp()
                <=> $right['occurred_at']->getTimestamp();

            if ($time !== 0) {
                return $time;
            }

            $type = $left['sort_type'] <=> $right['sort_type'];

            return $type !== 0
                ? $type
                : $left['source_id'] <=> $right['source_id'];
        });

        $running = $opening;
        $debits = 0.0;
        $credits = 0.0;

        foreach ($entries as &$entry) {
            $debits += $entry['debit'];
            $credits += $entry['credit'];
            $running = round(
                $running + $entry['debit'] - $entry['credit'],
                4,
            );
            $entry['balance'] = $running;
            unset($entry['sort_type'], $entry['source_id']);
        }
        unset($entry);

        return [
            'currency' => $currency,
            'opening_balance' => $opening,
            'debits' => round($debits, 4),
            'credits' => round($credits, 4),
            'closing_balance' => round($running, 4),
            'entries' => $entries,
        ];
    }
}
