<?php

namespace Database\Factories;

use App\Models\CurrentLocation;
use App\Models\Device;
use App\Models\Salesman;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CurrentLocation>
 */
class CurrentLocationFactory extends Factory
{
    protected $model = CurrentLocation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'user_id' => User::factory(),
            'salesman_id' => Salesman::factory(),
            'device_id' => Device::factory(),
            'latitude' => 34.5553,
            'longitude' => 69.2075,
            'horizontal_accuracy' => 10.5,
            'altitude' => 1800,
            'speed' => 0,
            'heading' => 180,
            'battery_level' => 85,
            'is_charging' => false,
            'network_status' => 'wifi',
            'is_mock_location' => false,
            'provider' => 'gps',
            'recorded_at' => now('UTC'),
            'received_at' => now('UTC'),
        ];
    }

    public function forTenant(int $tenantId): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $tenantId,
        ]);
    }

    public function forUser(int $userId): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $userId,
        ]);
    }

    public function forSalesman(int $salesmanId): static
    {
        return $this->state(fn (array $attributes) => [
            'salesman_id' => $salesmanId,
        ]);
    }

    public function recordedAt(string $recordedAt): static
    {
        return $this->state(fn (array $attributes) => [
            'recorded_at' => $recordedAt,
        ]);
    }
}
