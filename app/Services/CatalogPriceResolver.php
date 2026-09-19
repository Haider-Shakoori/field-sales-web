<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\PriceListItem;
use App\Models\Product;
use Carbon\CarbonInterface;

class CatalogPriceResolver
{
    public function resolve(
        Product $product,
        ?Customer $customer = null,
        float $quantity = 1,
        ?CarbonInterface $date = null,
    ): array {
        $quantity = max($quantity, 0.0001);
        $priceList = $customer?->priceList;

        if ($priceList
            && $priceList->is_active
            && $this->effectiveOn($priceList->effective_from, $priceList->effective_to, $date)) {
            $tier = PriceListItem::where('price_list_id', $priceList->id)
                ->where('product_id', $product->id)
                ->where('min_quantity', '<=', $quantity)
                ->orderByDesc('min_quantity')
                ->first();

            if ($tier) {
                return [
                    'unit_price' => (float) $tier->price,
                    'currency' => $priceList->currency,
                    'source' => 'price_list',
                    'price_list_id' => $priceList->uuid,
                    'price_list_item_id' => $tier->uuid,
                    'min_quantity' => (float) $tier->min_quantity,
                ];
            }
        }

        return [
            'unit_price' => (float) $product->base_price,
            'currency' => $product->currency,
            'source' => 'base_price',
            'price_list_id' => null,
            'price_list_item_id' => null,
            'min_quantity' => 1.0,
        ];
    }

    private function effectiveOn(
        ?CarbonInterface $from,
        ?CarbonInterface $to,
        ?CarbonInterface $date,
    ): bool {
        $date ??= today();

        return ($from === null || $from->startOfDay()->lte($date))
            && ($to === null || $to->endOfDay()->gte($date));
    }
}
