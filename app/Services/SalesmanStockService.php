<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use App\Models\SalesReturn;
use App\Models\Salesman;
use App\Models\SalesmanStockBalance;
use App\Models\SalesmanStockMovement;
use App\Models\StockIssue;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SalesmanStockService
{
    public function issue(
        Salesman $salesman,
        array $items,
        User $actor,
        CarbonImmutable $issuedAt,
        ?string $notes = null,
    ): StockIssue {
        return DB::transaction(function () use (
            $salesman,
            $items,
            $actor,
            $issuedAt,
            $notes,
        ): StockIssue {
            $issue = StockIssue::create([
                'salesman_id' => $salesman->id,
                'issue_number' => $this->nextIssueNumber($issuedAt),
                'issued_at' => $issuedAt,
                'notes' => $notes,
                'created_by' => $actor->id,
            ]);

            foreach ($items as $item) {
                $product = Product::active()
                    ->where('uuid', $item['product_id'])
                    ->firstOrFail();
                $quantity = round((float) $item['quantity'], 4);

                if ($quantity <= 0) {
                    throw ValidationException::withMessages([
                        'items' => 'Issued stock quantities must be greater than zero.',
                    ]);
                }

                $issueItem = $issue->items()->create([
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                ]);

                $this->change(
                    $salesman,
                    $product,
                    'sellable',
                    'issue',
                    $quantity,
                    'stock_issue',
                    $issue->id,
                    $issue->issue_number,
                    $issuedAt,
                    $actor,
                    $notes,
                );
            }

            return $issue->fresh()->load(['salesman.user', 'items.product']);
        });
    }

    public function applyApprovedOrder(Order $order, User $actor): void
    {
        $order->loadMissing(['salesman', 'items.product']);

        if (! $order->salesman) {
            throw ValidationException::withMessages([
                'status' => 'The order has no salesman stock account.',
            ]);
        }

        DB::transaction(function () use ($order, $actor): void {
            foreach ($order->items as $item) {
                $this->change(
                    $order->salesman,
                    $item->product,
                    'sellable',
                    'sale',
                    - round((float) $item->quantity, 4),
                    'order',
                    $order->id,
                    $order->order_number,
                    now()->toImmutable(),
                    $actor,
                    'Approved order stock deduction.',
                );
            }
        });
    }

    public function restoreCancelledOrder(Order $order, User $actor): void
    {
        $order->loadMissing(['salesman', 'items.product']);

        if (! $order->salesman) {
            return;
        }

        DB::transaction(function () use ($order, $actor): void {
            foreach ($order->items as $item) {
                $deducted = SalesmanStockMovement::query()
                    ->where('movement_type', 'sale')
                    ->where('reference_type', 'order')
                    ->where('reference_id', $order->id)
                    ->where('product_id', $item->product_id)
                    ->where('bucket', 'sellable')
                    ->exists();

                if (! $deducted) {
                    continue;
                }

                $this->change(
                    $order->salesman,
                    $item->product,
                    'sellable',
                    'order_cancel_restore',
                    round((float) $item->quantity, 4),
                    'order',
                    $order->id,
                    $order->order_number,
                    now()->toImmutable(),
                    $actor,
                    'Cancelled approved order stock restoration.',
                );
            }
        });
    }

    public function applyApprovedReturn(
        SalesReturn $return,
        User $actor,
    ): void {
        $return->loadMissing(['salesman', 'items.product']);

        DB::transaction(function () use ($return, $actor): void {
            foreach ($return->items as $item) {
                $bucket = $item->condition === 'damaged' ? 'damaged' : 'sellable';

                $this->change(
                    $return->salesman,
                    $item->product,
                    $bucket,
                    'customer_return',
                    round((float) $item->quantity, 4),
                    'sales_return',
                    $return->id,
                    $return->return_number,
                    $return->returned_at?->toImmutable() ?? now()->toImmutable(),
                    $actor,
                    $item->reason ?: $return->notes,
                );
            }
        });
    }

    public function balance(
        Salesman $salesman,
        Product $product,
    ): SalesmanStockBalance {
        return SalesmanStockBalance::firstOrCreate(
            [
                'salesman_id' => $salesman->id,
                'product_id' => $product->id,
            ],
            [
                'sellable_qty' => 0,
                'damaged_qty' => 0,
            ],
        );
    }

    private function change(
        Salesman $salesman,
        Product $product,
        string $bucket,
        string $movementType,
        float $quantityChange,
        ?string $referenceType,
        ?int $referenceId,
        ?string $referenceNumber,
        CarbonImmutable $occurredAt,
        ?User $actor,
        ?string $notes,
    ): void {
        $existing = SalesmanStockMovement::query()
            ->where('movement_type', $movementType)
            ->where('reference_type', $referenceType)
            ->where('reference_id', $referenceId)
            ->where('product_id', $product->id)
            ->where('bucket', $bucket)
            ->exists();

        if ($existing) {
            return;
        }

        SalesmanStockBalance::firstOrCreate(
            [
                'salesman_id' => $salesman->id,
                'product_id' => $product->id,
            ],
            [
                'sellable_qty' => 0,
                'damaged_qty' => 0,
            ],
        );

        $balance = SalesmanStockBalance::query()
            ->where('salesman_id', $salesman->id)
            ->where('product_id', $product->id)
            ->lockForUpdate()
            ->firstOrFail();

        $column = $bucket === 'damaged' ? 'damaged_qty' : 'sellable_qty';
        $next = round((float) $balance->{$column} + $quantityChange, 4);

        if ($next < 0) {
            throw ValidationException::withMessages([
                'stock' => sprintf(
                    'Insufficient %s stock for %s. Available %.4f, required %.4f.',
                    $bucket,
                    $product->name,
                    (float) $balance->{$column},
                    abs($quantityChange),
                ),
            ]);
        }

        $balance->update([$column => $next]);

        SalesmanStockMovement::create([
            'salesman_id' => $salesman->id,
            'product_id' => $product->id,
            'bucket' => $bucket,
            'movement_type' => $movementType,
            'quantity_change' => $quantityChange,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'reference_number' => $referenceNumber,
            'occurred_at' => $occurredAt,
            'notes' => $notes,
            'created_by' => $actor?->id,
        ]);
    }

    private function nextIssueNumber(CarbonImmutable $issuedAt): string
    {
        return sprintf(
            'ISS-%s-%s',
            $issuedAt->format('YmdHis'),
            strtoupper(substr(str_replace('-', '', (string) Str::uuid()), 0, 6)),
        );
    }
}
