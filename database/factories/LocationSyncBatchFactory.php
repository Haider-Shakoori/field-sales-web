<?php

namespace Database\Factories;

use App\Models\Device;
use App\Models\LocationSyncBatch;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<LocationSyncBatch>
 */
class LocationSyncBatchFactory extends Factory
{
    protected $model = LocationSyncBatch::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'device_id' => Device::factory(),
            'user_id' => User::factory(),
            'batch_uuid' => (string) Str::uuid(),
            'point_count' => 1,
            'received_at' => now('UTC'),
            'processed_at' => null,
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

    public function forDevice(int $deviceId): static
    {
        return $this->state(fn (array $attributes) => [
            'device_id' => $deviceId,
        ]);
    }
}
