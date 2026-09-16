<?php

namespace Database\Factories;

use App\Models\PriceList;
use App\Models\PriceListItem;
use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class PriceListItemFactory extends Factory
{
    protected $model = PriceListItem::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'price_list_id' => PriceList::factory(),
            'product_id' => Product::factory(),
            'price' => fake()->randomFloat(2, 5, 500),
        ];
    }

    public function forTenant(int $tenantId): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $tenantId,
        ]);
    }
}
