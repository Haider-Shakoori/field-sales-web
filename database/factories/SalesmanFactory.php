<?php

namespace Database\Factories;

use App\Models\Salesman;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class SalesmanFactory extends Factory
{
    protected $model = Salesman::class;

    public function definition(): array
    {
        return [
            'employee_code' => 'SLM-'.str()->random(6),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'phone' => fake()->phoneNumber(),
            'email' => fake()->unique()->safeEmail(),
            'hire_date' => fake()->dateTimeBetween('-2 years', 'now'),
            'designation' => fake()->jobTitle(),
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

    public function withUser(?User $user = null): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user?->id ?? User::factory(),
        ]);
    }
}
