<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerCategory;
use App\Models\Route;
use App\Models\Salesman;
use App\Models\Tenant;
use App\Models\Territory;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'branch_id' => Branch::factory(),
            'code' => fake()->unique()->bothify('CUS-######'),
            'business_name' => fake()->company(),
            'contact_person' => fake()->optional()->name(),
            'phone' => fake()->optional()->phoneNumber(),
            'whatsapp' => fake()->optional()->phoneNumber(),
            'category_id' => CustomerCategory::factory(),
            'province' => fake()->optional()->state(),
            'district' => fake()->optional()->city(),
            'address' => fake()->optional()->streetAddress(),
            'latitude' => fake()->optional()->latitude(-90, 90),
            'longitude' => fake()->optional()->longitude(-180, 180),
            'geofence_radius' => fake()->randomElement([50, 100, 150, 200]),
            'photo_url' => fake()->optional()->imageUrl(),
            'assigned_salesman_id' => Salesman::factory(),
            'territory_id' => Territory::factory(),
            'route_id' => Route::factory(),
            'credit_limit' => fake()->randomFloat(2, 0, 10000),
            'outstanding_balance' => fake()->randomFloat(2, 0, 5000),
            'price_list_id' => null,
            'visit_frequency' => fake()->optional()->randomElement(['daily', 'weekly', 'biweekly', 'monthly']),
            'is_active' => true,
            'notes' => fake()->optional()->paragraph(),
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

    public function withSalesman(int $salesmanId): static
    {
        return $this->state(fn (array $attributes) => [
            'assigned_salesman_id' => $salesmanId,
        ]);
    }

    public function forBranch(int $branchId): static
    {
        return $this->state(fn (array $attributes) => [
            'branch_id' => $branchId,
        ]);
    }

    public function forTerritory(int $territoryId): static
    {
        return $this->state(fn (array $attributes) => [
            'territory_id' => $territoryId,
        ]);
    }

    public function forSalesman(int $salesmanId): static
    {
        return $this->withSalesman($salesmanId);
    }

    public function withTerritory(int $territoryId): static
    {
        return $this->state(fn (array $attributes) => [
            'territory_id' => $territoryId,
        ]);
    }

    public function withRoute(int $routeId): static
    {
        return $this->state(fn (array $attributes) => [
            'route_id' => $routeId,
        ]);
    }

    public function withCoordinates(float $latitude, float $longitude): static
    {
        return $this->state(fn (array $attributes) => [
            'latitude' => $latitude,
            'longitude' => $longitude,
        ]);
    }
}
