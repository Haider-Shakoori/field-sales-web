<?php

namespace App\Services;

use App\Models\Promotion;
use Carbon\CarbonInterface;

final class PromotionEngine
{
    public function bonuses(array $preparedItems, float $grandTotal, CarbonInterface $at): array
    {
        $quantities = collect($preparedItems)->keyBy('product_id')->map(fn ($line) => (float) $line['quantity']);

        return Promotion::query()
            ->with('rewardProduct')
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $at))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', $at))
            ->get()
            ->filter(function (Promotion $promotion) use ($quantities, $grandTotal): bool {
                $quantityOk = $promotion->qualifying_product_id === null
                    || (float) ($quantities[$promotion->qualifying_product_id] ?? 0) >= (float) $promotion->qualifying_quantity;
                $amountOk = $promotion->minimum_order_amount === null
                    || $grandTotal >= (float) $promotion->minimum_order_amount;

                return $quantityOk && $amountOk;
            })
            ->filter(fn (Promotion $promotion): bool => $promotion->reward_type === 'product')
            ->map(function (Promotion $promotion): array {
                $product = $promotion->rewardProduct;

                return [
                    'product_id' => $product->id,
                    'product_sku' => $product->sku,
                    'product_name' => $product->name,
                    'unit' => $product->unit,
                    'quantity' => (float) $promotion->reward_quantity,
                    'unit_price' => 0,
                    'discount_percent' => 0,
                    'discount_amount' => 0,
                    'line_total' => 0,
                    'is_bonus' => true,
                    'promotion_id' => $promotion->id,
                ];
            })
            ->values()
            ->all();
    }
}
