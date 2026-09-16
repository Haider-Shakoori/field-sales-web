<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Route;
use App\Models\RouteCustomer;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class RouteCustomerFactory extends Factory
{
    protected $model = RouteCustomer::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'route_id' => Route::factory(),
            'customer_id' => Customer::factory(),
            'visit_order' => fake()->numberBetween(1, 50),
            'effective_from' => now()->toDateString(),
            'effective_to' => null,
        ];
    }

    public function forTenant(int $tenantId): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $tenantId,
        ]);
    }

    public function forRoute(int $routeId): static
    {
        return $this->state(fn (array $attributes) => [
            'route_id' => $routeId,
        ]);
    }

    public function forCustomer(int $customerId): static
    {
        return $this->state(fn (array $attributes) => [
            'customer_id' => $customerId,
        ]);
    }
}
