<?php

namespace App\Support\Pricing;

use App\Models\Customer;
use App\Models\PriceListItem;
use App\Models\Product;
use InvalidArgumentException;

class ProductPriceResolver
{
    /**
     * Resolve the effective price for a product for a given customer.
     *
     * A customer with an active assigned price list uses the price list
     * override for the product; otherwise the product's base price applies.
     * Price list items cannot be applied across tenants.
     *
     * @return string Decimal price formatted to two places.
     */
    public function resolve(Product $product, ?Customer $customer = null): string
    {
        if ($customer && $customer->tenant_id !== $product->tenant_id) {
            throw new InvalidArgumentException('Cross-tenant price resolution is not supported.');
        }

        if ($customer === null || $customer->price_list_id === null) {
            return $this->normalize($product->price);
        }

        $customer->loadMissing('priceList');

        if (! $customer->priceList?->is_active) {
            return $this->normalize($product->price);
        }

        $item = PriceListItem::query()
            ->where('tenant_id', $product->tenant_id)
            ->where('price_list_id', $customer->price_list_id)
            ->where('product_id', $product->id)
            ->first();

        return $item ? $this->normalize($item->price) : $this->normalize($product->price);
    }

    private function normalize(mixed $price): string
    {
        return number_format((float) $price, 2, '.', '');
    }
}
