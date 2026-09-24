<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Salesman;
use App\Models\SalesmanStockBalance;
use Carbon\CarbonImmutable;

class CustomerReorderRecommendationService
{
    public function __construct(private readonly StockSettingsService $stockSettings) {}

    public function recommend(
        Customer $customer,
        ?Salesman $salesman = null,
        int $lookbackDays = 365,
        ?CarbonImmutable $asOf = null,
    ): array {
        $lookbackDays = min(730, max(90, $lookbackDays));
        $asOf ??= CarbonImmutable::now();
        $from = $asOf->subDays($lookbackDays)->startOfDay();

        $orders = Order::query()
            ->with(['items.product'])
            ->where('customer_id', $customer->id)
            ->where('status', 'approved')
            ->whereBetween('ordered_at', [$from, $asOf->endOfDay()])
            ->orderBy('ordered_at')
            ->get();

        $events = [];
        foreach ($orders as $order) {
            foreach ($order->items as $item) {
                if (! $item->product?->is_active) {
                    continue;
                }

                $events[$item->product_id][] = [
                    'ordered_at' => CarbonImmutable::parse($order->ordered_at),
                    'quantity' => (float) $item->quantity,
                    'unit_price' => (float) $item->unit_price,
                    'currency' => $order->currency,
                    'product' => $item->product,
                ];
            }
        }

        $stockEnabled = $salesman !== null
            && $customer->tenant !== null
            && $this->stockSettings->enabled($customer->tenant);
        $stock = collect();

        if ($stockEnabled) {
            $stock = SalesmanStockBalance::query()
                ->where('salesman_id', $salesman->id)
                ->get()
                ->keyBy('product_id');
        }

        return collect($events)
            ->map(function (array $productEvents) use ($asOf, $stockEnabled, $stock): ?array {
                if (count($productEvents) < 2) {
                    return null;
                }

                usort($productEvents, fn (array $a, array $b) => $a['ordered_at']->getTimestamp() <=> $b['ordered_at']->getTimestamp());
                $recent = array_slice($productEvents, -6);
                $intervals = [];

                for ($i = 1; $i < count($productEvents); $i++) {
                    $intervals[] = max(1, (int) round($productEvents[$i - 1]['ordered_at']->diffInDays($productEvents[$i]['ordered_at'])));
                }

                $typicalInterval = (int) round((float) collect($intervals)->median());
                $typicalInterval = min(180, max(7, $typicalInterval));
                $averageQuantity = round(collect($recent)->avg('quantity'), 4);
                $last = end($productEvents);
                $nextDue = $last['ordered_at']->startOfDay()->addDays($typicalInterval);
                $daysUntilDue = (int) $asOf->startOfDay()->diffInDays($nextDue, false);
                $dueWindow = max(7, min(21, (int) round($typicalInterval * 0.25)));

                if ($daysUntilDue > $dueWindow) {
                    return null;
                }

                $available = null;
                $suggested = $averageQuantity;
                $stockLimited = false;

                if ($stockEnabled) {
                    $balance = $stock->get($last['product']->id);
                    $available = round(max(0, (float) ($balance?->sellable_qty ?? 0)), 4);
                    $suggested = round(min($averageQuantity, $available), 4);
                    $stockLimited = $available + 0.00001 < $averageQuantity;
                }

                $ordersCount = count($productEvents);
                $confidence = $ordersCount >= 5 ? 'high' : ($ordersCount >= 3 ? 'medium' : 'low');
                $overdueDays = max(0, -$daysUntilDue);
                $score = min(100, 40 + min(30, ($ordersCount - 2) * 10) + min(20, $overdueDays) + ($daysUntilDue <= 0 ? 10 : 0));
                $product = $last['product'];

                return [
                    'product_id' => $product->uuid,
                    'sku' => $product->sku,
                    'name' => $product->name,
                    'unit' => $product->unit,
                    'currency' => $last['currency'],
                    'last_unit_price' => $last['unit_price'],
                    'purchase_count' => $ordersCount,
                    'average_quantity' => $averageQuantity,
                    'demand_quantity' => $averageQuantity,
                    'suggested_quantity' => $suggested,
                    'typical_interval_days' => $typicalInterval,
                    'last_ordered_at' => $last['ordered_at']->toISOString(),
                    'next_due_date' => $nextDue->toDateString(),
                    'days_until_due' => $daysUntilDue,
                    'days_overdue' => $overdueDays,
                    'confidence' => $confidence,
                    'score' => $score,
                    'stock_enabled' => $stockEnabled,
                    'available_stock' => $available,
                    'stock_limited' => $stockLimited,
                    'reason' => $this->reason($ordersCount, $typicalInterval, $averageQuantity, $product->unit, $daysUntilDue, $stockLimited),
                ];
            })
            ->filter()
            ->sort(function (array $left, array $right): int {
                $due = $left['days_until_due'] <=> $right['days_until_due'];

                return $due !== 0 ? $due : ($right['score'] <=> $left['score']);
            })
            ->values()
            ->all();
    }

    private function reason(
        int $purchaseCount,
        int $intervalDays,
        float $quantity,
        string $unit,
        int $daysUntilDue,
        bool $stockLimited,
    ): string {
        $timing = $daysUntilDue < 0
            ? abs($daysUntilDue).' day(s) overdue'
            : ($daysUntilDue === 0 ? 'due today' : 'due in '.$daysUntilDue.' day(s)');
        $reason = "Ordered {$purchaseCount} times; typical interval {$intervalDays} days; {$timing}; recent average {$quantity} {$unit}.";

        return $stockLimited ? $reason.' Suggested quantity is capped by available salesman stock.' : $reason;
    }
}
