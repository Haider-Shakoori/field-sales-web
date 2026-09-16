<?php

namespace Database\Factories;

use App\Models\Route;
use App\Models\Tenant;
use App\Models\Territory;
use Illuminate\Database\Eloquent\Factories\Factory;

class RouteFactory extends Factory
{
    protected $model = Route::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'territory_id' => Territory::factory(),
            'name' => fake()->streetName().' Route',
            'code' => fake()->unique()->bothify('RTE-???'),
            'description' => fake()->optional()->sentence(),
            'weekday' => fake()->optional()->numberBetween(0, 6),
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

    public function forTerritory(int $territoryId): static
    {
        return $this->state(fn (array $attributes) => [
            'territory_id' => $territoryId,
        ]);
    }
}
