<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Device;
use App\Models\Salesman;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantProvisioningService;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class MobileFollowUpParityTest extends TestCase
{
    use RefreshDatabase;

    private ?string $token = null;

    public function test_salesman_follow_up_flow_is_idempotent_scoped_and_completable(): void
    {
        $fixture = $this->fixture();
        $uuid = (string) Str::uuid();

        $payload = [
            'offline_uuid' => $uuid,
            'type' => 'payment',
            'priority' => 'high',
            'due_at' => '2026-10-15T09:30:00Z',
            'notes' => 'Collect the overdue balance.',
        ];

        $this->postJson(
            '/api/v1/customers/'.$fixture['customer']->uuid.'/follow-ups',
            $payload,
            $this->headers(),
        )
            ->assertCreated()
            ->assertJsonPath('data.id', $uuid)
            ->assertJsonPath('data.customer_id', $fixture['customer']->uuid)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.priority', 'high');

        $this->postJson(
            '/api/v1/customers/'.$fixture['customer']->uuid.'/follow-ups',
            $payload,
            $this->headers(),
        )->assertOk()->assertJsonPath('data.id', $uuid);

        $this->getJson('/api/v1/follow-ups?status=pending', $this->headers())
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.customer_name', 'Parity Customer');

        $this->patchJson(
            '/api/v1/follow-ups/'.$uuid.'/status',
            [
                'status' => 'completed',
                'completion_note' => 'Payment collected.',
            ],
            $this->headers(),
        )
            ->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.completion_note', 'Payment collected.');

        $this->assertDatabaseCount('customer_follow_ups', 1);
        $this->assertDatabaseHas('customer_follow_ups', [
            'uuid' => $uuid,
            'assigned_salesman_id' => $fixture['salesman']->id,
            'status' => 'completed',
        ]);
    }

    public function test_mobile_fuel_requires_operational_evidence_fields(): void
    {
        $this->fixture();

        $base = [
            'offline_uuid' => (string) Str::uuid(),
            'spent_at' => '2026-10-01T10:00:00Z',
            'category' => 'fuel',
            'currency' => 'AFN',
            'amount' => 1500,
            'latitude' => 34.5553,
            'longitude' => 69.2075,
            'accuracy' => 8,
        ];

        $this->postJson('/api/v1/expenses', $base, $this->headers())
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonStructure([
                'error' => [
                    'details' => [
                        'fuel_liters',
                        'odometer_km',
                        'vehicle_reference',
                        'merchant',
                    ],
                ],
            ]);

        $valid = [
            ...$base,
            'offline_uuid' => (string) Str::uuid(),
            'fuel_liters' => 30,
            'odometer_km' => 22150,
            'vehicle_reference' => 'KBL-TEST-01',
            'merchant' => 'Parity Fuel Station',
            'full_tank' => true,
        ];

        $this->postJson('/api/v1/expenses', $valid, $this->headers())
            ->assertCreated()
            ->assertJsonPath('data.category', 'fuel')
            ->assertJsonPath('data.vehicle_reference', 'KBL-TEST-01');
    }

    private function fixture(): array
    {
        $context = app(TenantContext::class);
        $tenant = $context->withPlatformScope(fn () => Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Parity Tenant',
            'slug' => 'parity-'.Str::lower(Str::random(6)),
            'timezone' => 'Asia/Kabul',
            'subscription_status' => 'active',
        ]));

        return $context->withTenant($tenant, function () use ($tenant): array {
            $roles = app(TenantProvisioningService::class)->provisionRbac($tenant);
            $branch = Branch::create([
                'name' => 'Parity Branch',
                'code' => 'PARITY',
                'is_active' => true,
            ]);
            $user = User::create([
                'uuid' => (string) Str::uuid(),
                'branch_id' => $branch->id,
                'name' => 'Parity Salesman',
                'email' => 'parity-salesman@example.test',
                'password' => Hash::make('password'),
                'role' => 'salesman',
                'is_active' => true,
            ]);
            $user->syncPrimaryRole($roles['salesman']);

            $salesman = Salesman::create([
                'user_id' => $user->id,
                'employee_code' => 'PAR-S1',
                'first_name' => 'Parity',
                'last_name' => 'Salesman',
                'is_active' => true,
            ]);
            $customer = Customer::create([
                'uuid' => (string) Str::uuid(),
                'branch_id' => $branch->id,
                'assigned_salesman_id' => $salesman->id,
                'code' => 'PAR-C1',
                'name' => 'Parity Customer',
                'created_by' => $user->id,
                'is_active' => true,
            ]);
            $device = Device::create([
                'user_id' => $user->id,
                'salesman_id' => $salesman->id,
                'device_uuid' => 'parity-device',
                'installation_uuid' => 'parity-install',
                'is_active' => true,
            ]);
            $this->token = $user->createToken('mobile-'.$device->uuid)->plainTextToken;

            return compact('tenant', 'branch', 'user', 'salesman', 'customer', 'device');
        });
    }

    private function headers(): array
    {
        return [
            'Authorization' => 'Bearer '.$this->token,
            'X-Device-UUID' => 'parity-device',
            'X-Installation-UUID' => 'parity-install',
            'X-App-Version' => '1.0.2',
            'X-Platform' => 'android',
            'X-OS-Version' => '16',
        ];
    }
}
