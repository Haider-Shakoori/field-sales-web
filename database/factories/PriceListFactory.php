<?php

namespace Database\Factories;

use App\Models\PriceList;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class PriceListFactory extends Factory
{
    protected $model = PriceList::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->randomElement(['Standard', 'Wholesale', 'Retail', 'Distributor', 'Promo']).' Price List',
            'is_default' => false,
            'is_active' => true,
        ];
    }

    public function default(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_default' => true,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function forTenant(int $tenantId): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $tenantId,
        ]);
    }
}
