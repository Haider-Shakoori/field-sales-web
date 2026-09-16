<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\CustomerLocationHistory;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomerLocationHistoryFactory extends Factory
{
    protected $model = CustomerLocationHistory::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'customer_id' => Customer::factory(),
            'latitude' => fake()->latitude(-90, 90),
            'longitude' => fake()->longitude(-180, 180),
            'address' => fake()->optional()->streetAddress(),
            'changed_by' => User::factory(),
            'changed_at' => now(),
        ];
    }

    public function forTenant(int $tenantId): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $tenantId,
        ]);
    }

    public function forCustomer(int $customerId): static
    {
        return $this->state(fn (array $attributes) => [
            'customer_id' => $customerId,
        ]);
    }
}
