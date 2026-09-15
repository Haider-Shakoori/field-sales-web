<?php

namespace Database\Factories;

use App\Models\Device;
use App\Models\Salesman;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class DeviceFactory extends Factory
{
    protected $model = Device::class;

    public function definition(): array
    {
        return [
            'device_uuid' => Str::uuid()->toString(),
            'installation_uuid' => Str::uuid()->toString(),
            'device_model' => fake()->randomElement(['Samsung Galaxy S24', 'Google Pixel 8', 'Xiaomi 14', 'OnePlus 12']),
            'manufacturer' => fake()->randomElement(['Samsung', 'Google', 'Xiaomi', 'OnePlus']),
            'android_version' => fake()->randomElement(['14', '13', '12']),
            'app_version' => '1.0.'.fake()->numberBetween(0, 9),
            'push_token' => 'fcm_'.Str::random(100),
            'fcm_token' => 'fcm_'.Str::random(100),
            'is_active' => true,
            'registered_at' => now()->subDays(fake()->numberBetween(0, 30)),
            'last_seen_at' => now()->subMinutes(fake()->numberBetween(0, 60)),
            'revoked_at' => null,
        ];
    }

    public function revoked(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
            'revoked_at' => now('UTC'),
        ]);
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

    public function withSalesman(?Salesman $salesman = null): static
    {
        return $this->state(fn (array $attributes) => [
            'salesman_id' => $salesman?->id,
        ]);
    }
}
