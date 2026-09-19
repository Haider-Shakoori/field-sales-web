<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\PriceList;
use App\Models\PriceListItem;
use App\Models\Product;
use Carbon\CarbonInterface;

class OrderPricingService
{
    public function price(
        Customer $customer,
        Product $product,
        float $quantity,
        CarbonInterface $orderedAt,
    ): array {
        $priceList = $this->effectivePriceList($customer, $orderedAt);

        if ($priceList) {
            $tier = PriceListItem::where('price_list_id', $priceList->id)
                ->where('product_id', $product->id)
                ->where('min_quantity', '<=', $quantity)
                ->orderByDesc('min_quantity')
                ->first();

            if ($tier) {
                return [
                    'unit_price' => round((float) $tier->price, 4),
                    'currency' => $priceList->currency,
                    'price_list_id' => $priceList->id,
                ];
            }
        }

        return [
            'unit_price' => round((float) $product->base_price, 4),
            'currency' => $product->currency,
            'price_list_id' => $priceList?->id,
        ];
    }

    public function effectivePriceList(
        Customer $customer,
        CarbonInterface $orderedAt,
    ): ?PriceList {
        if (! $customer->price_list_id) {
            return null;
        }

        return PriceList::active()
            ->effectiveOn($orderedAt->toDateString())
            ->whereKey($customer->price_list_id)
            ->first();
    }
}
