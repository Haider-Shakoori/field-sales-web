<?php

namespace App\Services;

use App\Models\Collection as CustomerCollection;
use App\Models\Expense;
use App\Models\OperationalAnomaly;
use App\Models\Order;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class OperationalAnomalyDetector
{
    public function detect(Collection $salesmanIds, CarbonImmutable $start, CarbonImmutable $end): void
    {
        if ($salesmanIds->isEmpty()) {
            return;
        }

        $this->collectionSignals($salesmanIds, $start, $end);
        $this->expenseOutliers($salesmanIds, $start, $end);
        $this->orderOutliers($salesmanIds, $start, $end);
    }

    private function collectionSignals(Collection $salesmanIds, CarbonImmutable $start, CarbonImmutable $end): void
    {
        $rows = CustomerCollection::query()
            ->with(['customer', 'salesman'])
            ->whereIn('salesman_id', $salesmanIds)
            ->where('collected_at', '>=', $start)
            ->where('collected_at', '<', $end)
            ->where(function ($query): void {
                $query->where('overpayment_flag', true)
                    ->orWhere('within_geofence', false);
            })
            ->get();

        foreach ($rows as $collection) {
            if ($collection->overpayment_flag) {
                $this->persist(
                    entityType: 'collection',
                    entityId: $collection->id,
                    salesmanId: $collection->salesman_id,
                    ruleCode: 'collection_overpayment',
                    severity: 'high',
                    title: 'Collection exceeds available balance',
                    summary: 'A collection was recorded above the customer balance available to collect.',
                    occurredAt: CarbonImmutable::parse($collection->collected_at),
                    evidence: [
                        'receipt_number' => $collection->receipt_number,
                        'customer' => $collection->customer?->name,
                        'salesman' => $collection->salesman?->full_name,
                        'amount' => (float) $collection->amount,
                        'balance_before' => (float) $collection->balance_before,
                        'currency' => $collection->currency,
                        'within_geofence' => $collection->within_geofence,
                    ],
                );
            }

            if ($collection->within_geofence === false) {
                $this->persist(
                    entityType: 'collection',
                    entityId: $collection->id,
                    salesmanId: $collection->salesman_id,
                    ruleCode: 'collection_outside_geofence',
                    severity: $collection->overpayment_flag ? 'high' : 'medium',
                    title: 'Collection recorded outside customer geofence',
                    summary: 'Collection GPS evidence was outside the configured customer geofence.',
                    occurredAt: CarbonImmutable::parse($collection->collected_at),
                    evidence: [
                        'receipt_number' => $collection->receipt_number,
                        'customer' => $collection->customer?->name,
                        'salesman' => $collection->salesman?->full_name,
                        'distance_meters' => $collection->distance_meters === null ? null : (float) $collection->distance_meters,
                        'accuracy_meters' => (float) $collection->accuracy,
                        'latitude' => (float) $collection->latitude,
                        'longitude' => (float) $collection->longitude,
                    ],
                );
            }
        }
    }

    private function expenseOutliers(Collection $salesmanIds, CarbonImmutable $start, CarbonImmutable $end): void
    {
        $rows = Expense::query()
            ->with('salesman')
            ->whereIn('salesman_id', $salesmanIds)
            ->whereIn('status', ['pending', 'approved'])
            ->where('spent_at', '>=', $start)
            ->where('spent_at', '<', $end)
            ->get();

        $rows->groupBy(fn (Expense $expense) => implode('|', [
            $expense->salesman_id,
            $expense->category,
            strtoupper((string) $expense->currency),
        ]))->each(function (Collection $group): void {
            if ($group->count() < 5) {
                return;
            }

            $median = $this->median($group->map(fn (Expense $expense) => (float) $expense->amount));
            if ($median <= 0) {
                return;
            }

            foreach ($group as $expense) {
                $amount = (float) $expense->amount;
                $factor = $amount / $median;
                if ($factor < 3) {
                    continue;
                }

                $this->persist(
                    entityType: 'expense',
                    entityId: $expense->id,
                    salesmanId: $expense->salesman_id,
                    ruleCode: 'expense_amount_outlier',
                    severity: $factor >= 5 ? 'high' : 'medium',
                    title: 'Expense is unusually high for its peer history',
                    summary: 'Expense amount is at least three times the median for the same salesman, category and currency in the selected period.',
                    occurredAt: CarbonImmutable::parse($expense->spent_at),
                    evidence: [
                        'expense_number' => $expense->expense_number,
                        'salesman' => $expense->salesman?->full_name,
                        'category' => $expense->category,
                        'amount' => $amount,
                        'currency' => $expense->currency,
                        'comparison_median' => round($median, 4),
                        'multiple_of_median' => round($factor, 2),
                        'sample_size' => $group->count(),
                        'status' => $expense->status,
                    ],
                );
            }
        });
    }

    private function orderOutliers(Collection $salesmanIds, CarbonImmutable $start, CarbonImmutable $end): void
    {
        $rows = Order::query()
            ->with(['customer', 'salesman'])
            ->whereIn('salesman_id', $salesmanIds)
            ->whereIn('status', ['pending', 'approved'])
            ->where('ordered_at', '>=', $start)
            ->where('ordered_at', '<', $end)
            ->get();

        $rows->groupBy(fn (Order $order) => implode('|', [
            $order->customer_id,
            strtoupper((string) $order->currency),
        ]))->each(function (Collection $group): void {
            if ($group->count() < 5) {
                return;
            }

            $median = $this->median($group->map(fn (Order $order) => (float) $order->grand_total));
            if ($median <= 0) {
                return;
            }

            foreach ($group as $order) {
                $amount = (float) $order->grand_total;
                $factor = $amount / $median;
                if ($factor < 3) {
                    continue;
                }

                $this->persist(
                    entityType: 'order',
                    entityId: $order->id,
                    salesmanId: $order->salesman_id,
                    ruleCode: 'order_value_outlier',
                    severity: $factor >= 5 ? 'high' : 'medium',
                    title: 'Order value is unusually high for this customer',
                    summary: 'Order total is at least three times the customer median in the same currency for the selected period.',
                    occurredAt: CarbonImmutable::parse($order->ordered_at),
                    evidence: [
                        'order_number' => $order->order_number,
                        'customer' => $order->customer?->name,
                        'salesman' => $order->salesman?->full_name,
                        'amount' => $amount,
                        'currency' => $order->currency,
                        'comparison_median' => round($median, 4),
                        'multiple_of_median' => round($factor, 2),
                        'sample_size' => $group->count(),
                        'status' => $order->status,
                    ],
                );
            }
        });
    }

    private function persist(
        string $entityType,
        int $entityId,
        ?int $salesmanId,
        string $ruleCode,
        string $severity,
        string $title,
        string $summary,
        CarbonImmutable $occurredAt,
        array $evidence,
    ): void {
        $fingerprint = hash('sha256', implode('|', [$ruleCode, $entityType, $entityId]));
        $existing = OperationalAnomaly::where('fingerprint', $fingerprint)->first();

        if ($existing) {
            $existing->update([
                'severity' => $severity,
                'title' => $title,
                'summary' => $summary,
                'evidence' => $evidence,
                'last_detected_at' => now(),
            ]);

            return;
        }

        OperationalAnomaly::create([
            'salesman_id' => $salesmanId,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'rule_code' => $ruleCode,
            'severity' => $severity,
            'state' => 'open',
            'title' => $title,
            'summary' => $summary,
            'evidence' => $evidence,
            'fingerprint' => $fingerprint,
            'occurred_at' => $occurredAt,
            'first_detected_at' => now(),
            'last_detected_at' => now(),
        ]);
    }

    private function median(Collection $values): float
    {
        $sorted = $values->map(fn ($value) => (float) $value)->sort()->values();
        $count = $sorted->count();
        if ($count === 0) {
            return 0.0;
        }

        $middle = intdiv($count, 2);

        return $count % 2 === 1
            ? (float) $sorted[$middle]
            : (((float) $sorted[$middle - 1] + (float) $sorted[$middle]) / 2);
    }
}
