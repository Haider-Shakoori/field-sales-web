<?php

namespace App\Services;

use App\Models\OrderItem;
use App\Models\Salesman;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class ReorderRecommendationService
{
    public function forTenant(?Salesman $salesman = null, int $lookbackDays = 180): Collection
    {
        $from = now()->subDays(max(60, min(365, $lookbackDays)));
        $items = OrderItem::query()
            ->with(['order.customer', 'order.salesman', 'product'])
            ->whereHas('order', function ($query) use ($from, $salesman): void {
                $query->where('status', 'approved')->where('ordered_at', '>=', $from);
                if ($salesman) {
                    $query->where('salesman_id', $salesman->id);
                }
            })
            ->get()
            ->filter(fn (OrderItem $item) => $item->order?->customer && $item->product);

        return $items
            ->groupBy(fn (OrderItem $item) => $item->order->customer_id.':'.$item->product_id)
            ->map(function (Collection $group): ?array {
                $events = $group
                    ->groupBy('order_id')
                    ->map(function (Collection $lines): array {
                        $order = $lines->first()->order;
                        return [
                            'date' => CarbonImmutable::parse($order->ordered_at),
                            'quantity' => (float) $lines->sum(fn ($line) => (float) $line->quantity),
                            'value' => (float) $lines->sum(fn ($line) => (float) $line->line_total),
                        ];
                    })
                    ->sortBy('date')
                    ->values();

                if ($events->isEmpty()) {
                    return null;
                }

                $intervals = [];
                for ($i = 1; $i < $events->count(); $i++) {
                    $intervals[] = max(1, $events[$i - 1]['date']->diffInDays($events[$i]['date']));
                }

                $averageInterval = $intervals === []
                    ? 30
                    : max(1, (int) round(array_sum($intervals) / count($intervals)));
                $last = $events->last();
                $daysSince = $last['date']->diffInDays(now());
                $dueRatio = $daysSince / $averageInterval;

                if ($events->count() < 2 && $daysSince < 45) {
                    return null;
                }
                if ($events->count() >= 2 && $dueRatio < .80) {
                    return null;
                }

                $first = $group->first();
                $recent = $events->take(-3);
                $suggestedQty = round($recent->avg('quantity'), 2);
                $confidence = min(95, 35 + ($events->count() * 12));

                return [
                    'customer_id' => $first->order->customer->uuid,
                    'customer_name' => $first->order->customer->name,
                    'salesman_id' => $first->order->salesman?->uuid,
                    'product_id' => $first->product->uuid,
                    'product_sku' => $first->product->sku,
                    'product_name' => $first->product->name,
                    'orders_count' => $events->count(),
                    'average_interval_days' => $averageInterval,
                    'days_since_last_order' => $daysSince,
                    'last_order_at' => $last['date']->toISOString(),
                    'suggested_order_date' => $last['date']->addDays($averageInterval)->toDateString(),
                    'suggested_quantity' => $suggestedQty,
                    'confidence' => $confidence,
                    'priority' => $dueRatio >= 1.25 ? 'overdue' : 'due_soon',
                    'reason' => $events->count() >= 2
                        ? "Usually reordered about every {$averageInterval} days; last purchase was {$daysSince} days ago."
                        : "Only one recent purchase; {$daysSince} days have passed, so a follow-up is due.",
                ];
            })
            ->filter()
            ->sortByDesc(fn (array $row) => ($row['priority'] === 'overdue' ? 100000 : 0) + $row['days_since_last_order'])
            ->values();
    }
}