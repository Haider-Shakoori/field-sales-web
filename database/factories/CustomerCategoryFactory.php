<?php

namespace Database\Factories;

use App\Models\CustomerCategory;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CustomerCategoryFactory extends Factory
{
    protected $model = CustomerCategory::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'uuid' => Str::uuid(),
            'name' => fake()->randomElement(['Wholesale', 'Retail', 'Key Account', 'Distributor', 'Online']),
            'description' => fake()->optional()->sentence(),
            'is_active' => true,
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
