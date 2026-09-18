<?php

namespace Database\Factories;

use App\Models\Device;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkSession;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<WorkSession>
 */
class WorkSessionFactory extends Factory
{
    protected $model = WorkSession::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'tenant_id' => Tenant::factory(),
            'user_id' => User::factory(),
            'salesman_id' => null,
            'device_id' => Device::factory(),
            'date' => now('UTC')->toDateString(),
            'start_time' => now('UTC'),
            'end_time' => null,
            'start_latitude' => 34.5553,
            'start_longitude' => 69.2075,
            'end_latitude' => null,
            'end_longitude' => null,
            'status' => WorkSession::STATUS_ACTIVE,
            'duration_minutes' => null,
            'is_late_start' => false,
            'is_early_finish' => false,
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

    public function forDevice(int $deviceId): static
    {
        return $this->state(fn (array $attributes) => [
            'device_id' => $deviceId,
        ]);
    }

    public function onDate(string $date): static
    {
        return $this->state(fn (array $attributes) => [
            'date' => $date,
        ]);
    }

    public function completed(?int $durationMinutes = null): static
    {
        return $this->state(fn (array $attributes) => [
            'end_time' => now('UTC'),
            'status' => WorkSession::STATUS_COMPLETED,
            'duration_minutes' => $durationMinutes,
            'end_latitude' => 34.5600,
            'end_longitude' => 69.2100,
        ]);
    }
}
