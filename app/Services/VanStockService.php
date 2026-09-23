<?php

namespace App\Services;

use App\Models\CustomerReturn;
use App\Models\Order;
use App\Models\Product;
use App\Models\Salesman;
use App\Models\SalesmanStockBalance;
use App\Models\SalesmanStockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VanStockService
{
    public function adjust(
        Salesman $salesman,
        Product $product,
        string $bucket,
        float $quantity,
        User $actor,
        string $note,
    ): SalesmanStockBalance {
        if (! in_array($bucket, ['sellable', 'damaged'], true)) {
            throw ValidationException::withMessages([
                'bucket' => 'Stock bucket must be sellable or damaged.',
            ]);
        }

        if (abs($quantity) < 0.0001) {
            throw ValidationException::withMessages([
                'quantity' => 'Stock adjustment quantity cannot be zero.',
            ]);
        }

        return DB::transaction(function () use (
            $salesman,
            $product,
            $bucket,
            $quantity,
            $actor,
            $note,
        ): SalesmanStockBalance {
            $balance = $this->balanceForUpdate($salesman, $product);

            if ($bucket === 'sellable') {
                $next = round((float) $balance->sellable_quantity + $quantity, 4);

                if ($next < (float) $balance->reserved_quantity) {
                    throw ValidationException::withMessages([
                        'quantity' => 'Sellable stock cannot be reduced below the quantity reserved by pending orders.',
                    ]);
                }

                if ($next < 0) {
                    throw ValidationException::withMessages([
                        'quantity' => 'Sellable stock cannot become negative.',
                    ]);
                }

                $balance->sellable_quantity = $next;
            } else {
                $next = round((float) $balance->damaged_quantity + $quantity, 4);

                if ($next < 0) {
                    throw ValidationException::withMessages([
                        'quantity' => 'Damaged stock cannot become negative.',
                    ]);
                }

                $balance->damaged_quantity = $next;
            }

            $balance->save();

            $this->movement(
                $salesman,
                $product,
                $quantity >= 0 ? 'manual_in' : 'manual_out',
                $bucket === 'sellable' ? $quantity : 0,
                0,
                $bucket === 'damaged' ? $quantity : 0,
                $note,
                $actor,
            );

            return $balance->fresh('product');
        });
    }

    public function reserveOrder(Order $order, ?User $actor = null): void
    {
        $order->loadMissing(['salesman', 'items.product']);

        if (! $order->salesman?->van_stock_enabled) {
            return;
        }

        foreach ($order->items as $item) {
            if ($this->orderMovementExists($order, $item->product_id, 'order_reserved')) {
                continue;
            }

            $balance = $this->balanceForUpdate($order->salesman, $item->product);
            $quantity = (float) $item->quantity;
            $available = $balance->available_quantity;

            if ($available + 0.0001 < $quantity) {
                throw ValidationException::withMessages([
                    'items' => sprintf(
                        'Insufficient van stock for %s. Available %.4f %s, requested %.4f %s.',
                        $item->product_name,
                        $available,
                        $item->unit,
                        $quantity,
                        $item->unit,
                    ),
                ]);
            }

            $balance->reserved_quantity = round(
                (float) $balance->reserved_quantity + $quantity,
                4,
            );
            $balance->save();

            $this->movement(
                $order->salesman,
                $item->product,
                'order_reserved',
                0,
                $quantity,
                0,
                'Reserved for '.$order->order_number,
                $actor,
                $order,
            );
        }
    }

    public function approveOrder(Order $order, ?User $actor = null): void
    {
        $order->loadMissing(['salesman', 'items.product']);

        if (! $order->salesman?->van_stock_enabled) {
            return;
        }

        $this->reserveOrder($order, $actor);

        foreach ($order->items as $item) {
            if ($this->orderMovementExists($order, $item->product_id, 'order_sold')) {
                continue;
            }

            $balance = $this->balanceForUpdate($order->salesman, $item->product);
            $quantity = (float) $item->quantity;

            if (
                (float) $balance->reserved_quantity + 0.0001 < $quantity
                || (float) $balance->sellable_quantity + 0.0001 < $quantity
            ) {
                throw ValidationException::withMessages([
                    'status' => 'Reserved van stock is no longer sufficient to approve this order.',
                ]);
            }

            $balance->reserved_quantity = round(
                (float) $balance->reserved_quantity - $quantity,
                4,
            );
            $balance->sellable_quantity = round(
                (float) $balance->sellable_quantity - $quantity,
                4,
            );
            $balance->save();

            $this->movement(
                $order->salesman,
                $item->product,
                'order_sold',
                -$quantity,
                -$quantity,
                0,
                'Consumed by approved '.$order->order_number,
                $actor,
                $order,
            );
        }
    }

    public function releaseOrder(Order $order, ?User $actor = null): void
    {
        $order->loadMissing(['salesman', 'items.product']);

        if (! $order->salesman?->van_stock_enabled) {
            return;
        }

        foreach ($order->items as $item) {
            $reserved = $this->orderMovementExists(
                $order,
                $item->product_id,
                'order_reserved',
            );
            $released = $this->orderMovementExists(
                $order,
                $item->product_id,
                'order_released',
            );
            $sold = $this->orderMovementExists(
                $order,
                $item->product_id,
                'order_sold',
            );

            if (! $reserved || $released || $sold) {
                continue;
            }

            $balance = $this->balanceForUpdate($order->salesman, $item->product);
            $quantity = min(
                (float) $item->quantity,
                (float) $balance->reserved_quantity,
            );

            $balance->reserved_quantity = round(
                (float) $balance->reserved_quantity - $quantity,
                4,
            );
            $balance->save();

            $this->movement(
                $order->salesman,
                $item->product,
                'order_released',
                0,
                -$quantity,
                0,
                'Released from '.$order->order_number,
                $actor,
                $order,
            );
        }
    }

    public function restockCancelledOrder(Order $order, ?User $actor = null): void
    {
        $order->loadMissing(['salesman', 'items.product']);

        if (! $order->salesman?->van_stock_enabled) {
            return;
        }

        foreach ($order->items as $item) {
            $sold = $this->orderMovementExists(
                $order,
                $item->product_id,
                'order_sold',
            );
            $restocked = $this->orderMovementExists(
                $order,
                $item->product_id,
                'order_cancelled_restock',
            );

            if (! $sold || $restocked) {
                continue;
            }

            $balance = $this->balanceForUpdate($order->salesman, $item->product);
            $quantity = (float) $item->quantity;

            $balance->sellable_quantity = round(
                (float) $balance->sellable_quantity + $quantity,
                4,
            );
            $balance->save();

            $this->movement(
                $order->salesman,
                $item->product,
                'order_cancelled_restock',
                $quantity,
                0,
                0,
                'Restocked after cancellation of '.$order->order_number,
                $actor,
                $order,
            );
        }
    }

    public function approveReturn(CustomerReturn $return, User $actor): void
    {
        $return->loadMissing(['salesman', 'items.product']);

        foreach ($return->items as $item) {
            $type = 'customer_return_'.$item->condition;

            if (SalesmanStockMovement::query()
                ->where('customer_return_id', $return->id)
                ->where('product_id', $item->product_id)
                ->where('movement_type', $type)
                ->exists()) {
                continue;
            }

            $balance = $this->balanceForUpdate($return->salesman, $item->product);
            $quantity = (float) $item->quantity;

            if ($item->condition === 'sellable') {
                $balance->sellable_quantity = round(
                    (float) $balance->sellable_quantity + $quantity,
                    4,
                );
            } else {
                $balance->damaged_quantity = round(
                    (float) $balance->damaged_quantity + $quantity,
                    4,
                );
            }

            $balance->save();

            $this->movement(
                $return->salesman,
                $item->product,
                $type,
                $item->condition === 'sellable' ? $quantity : 0,
                0,
                $item->condition === 'damaged' ? $quantity : 0,
                'Approved '.$return->return_number,
                $actor,
                null,
                $return,
            );
        }
    }

    public function remainingReturnable(Order $order, Product $product): float
    {
        $ordered = (float) $order->items()
            ->where('product_id', $product->id)
            ->sum('quantity');

        $alreadyReturned = (float) DB::table('customer_return_items')
            ->join(
                'customer_returns',
                'customer_returns.id',
                '=',
                'customer_return_items.customer_return_id',
            )
            ->where('customer_returns.tenant_id', $order->tenant_id)
            ->where('customer_returns.order_id', $order->id)
            ->whereIn('customer_returns.status', ['pending', 'approved'])
            ->where('customer_return_items.product_id', $product->id)
            ->sum('customer_return_items.quantity');

        return max(0, round($ordered - $alreadyReturned, 4));
    }

    private function orderMovementExists(
        Order $order,
        int $productId,
        string $type,
    ): bool {
        return SalesmanStockMovement::query()
            ->where('order_id', $order->id)
            ->where('product_id', $productId)
            ->where('movement_type', $type)
            ->exists();
    }

    private function balanceForUpdate(
        Salesman $salesman,
        Product $product,
    ): SalesmanStockBalance {
        SalesmanStockBalance::firstOrCreate(
            [
                'salesman_id' => $salesman->id,
                'product_id' => $product->id,
            ],
            [
                'sellable_quantity' => 0,
                'reserved_quantity' => 0,
                'damaged_quantity' => 0,
            ],
        );

        return SalesmanStockBalance::query()
            ->where('salesman_id', $salesman->id)
            ->where('product_id', $product->id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function movement(
        Salesman $salesman,
        Product $product,
        string $type,
        float $sellableDelta,
        float $reservedDelta,
        float $damagedDelta,
        ?string $note,
        ?User $actor,
        ?Order $order = null,
        ?CustomerReturn $return = null,
    ): SalesmanStockMovement {
        return SalesmanStockMovement::create([
            'salesman_id' => $salesman->id,
            'product_id' => $product->id,
            'order_id' => $order?->id,
            'customer_return_id' => $return?->id,
            'movement_type' => $type,
            'sellable_delta' => round($sellableDelta, 4),
            'reserved_delta' => round($reservedDelta, 4),
            'damaged_delta' => round($damagedDelta, 4),
            'note' => $note,
            'occurred_at' => now(),
            'created_by' => $actor?->id,
        ]);
    }
}
