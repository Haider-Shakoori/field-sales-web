<?php

namespace Database\Factories;

use App\Models\Device;
use App\Models\PrivacyAcknowledgement;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PrivacyAcknowledgement>
 */
class PrivacyAcknowledgementFactory extends Factory
{
    protected $model = PrivacyAcknowledgement::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'tenant_id' => Tenant::factory(),
            'user_id' => User::factory(),
            'device_id' => Device::factory(),
            'policy_version' => '1',
            'acknowledged_at' => now('UTC'),
            'app_version' => '1.0.0',
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

    public function policyVersion(string $version): static
    {
        return $this->state(fn (array $attributes) => [
            'policy_version' => $version,
        ]);
    }
}
