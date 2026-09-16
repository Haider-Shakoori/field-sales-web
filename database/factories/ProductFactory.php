<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->words(3, true),
            'sku' => fake()->unique()->bothify('SKU-####-????'),
            'unit' => fake()->randomElement(Product::UNITS),
            'price' => fake()->randomFloat(2, 5, 500),
            'is_active' => true,
            'category' => fake()->optional()->randomElement(['Food', 'Beverage', 'Household', 'Personal Care']),
        ];
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
