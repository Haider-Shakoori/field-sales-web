<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Supervisor;
use App\Models\SupervisorAssignment;
use App\Models\Tenant;
use App\Models\Territory;
use Illuminate\Database\Eloquent\Factories\Factory;

class SupervisorAssignmentFactory extends Factory
{
    protected $model = SupervisorAssignment::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'supervisor_id' => Supervisor::factory(),
            'branch_id' => Branch::factory(),
            'territory_id' => Territory::factory(),
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

    public function forSupervisor(int $supervisorId): static
    {
        return $this->state(fn (array $attributes) => [
            'supervisor_id' => $supervisorId,
        ]);
    }

    public function withTerritory(int $territoryId): static
    {
        return $this->state(fn (array $attributes) => [
            'territory_id' => $territoryId,
        ]);
    }

    public function ended(string $effectiveTo): static
    {
        return $this->state(fn (array $attributes) => [
            'effective_to' => $effectiveTo,
        ]);
    }
}
