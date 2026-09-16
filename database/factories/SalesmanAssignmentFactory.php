<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Route;
use App\Models\Salesman;
use App\Models\SalesmanAssignment;
use App\Models\Supervisor;
use App\Models\Tenant;
use App\Models\Territory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class SalesmanAssignmentFactory extends Factory
{
    protected $model = SalesmanAssignment::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'salesman_id' => Salesman::factory(),
            'branch_id' => Branch::factory(),
            'territory_id' => Territory::factory(),
            'route_id' => Route::factory(),
            'supervisor_id' => Supervisor::factory(),
            'effective_from' => now()->toDateString(),
            'effective_to' => null,
            'created_by' => User::factory(),
        ];
    }

    public function forTenant(int $tenantId): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $tenantId,
        ]);
    }

    public function forSalesman(int $salesmanId): static
    {
        return $this->state(fn (array $attributes) => [
            'salesman_id' => $salesmanId,
        ]);
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

    public function withSupervisor(int $supervisorId): static
    {
        return $this->state(fn (array $attributes) => [
            'supervisor_id' => $supervisorId,
        ]);
    }

    public function ended(string $effectiveTo): static
    {
        return $this->state(fn (array $attributes) => [
            'effective_to' => $effectiveTo,
        ]);
    }
}
