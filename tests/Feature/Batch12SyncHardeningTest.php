<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerVisit;
use App\Models\Device;
use App\Models\Salesman;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class Batch12SyncHardeningTest extends TestCase
{
    use RefreshDatabase;

    private ?string $token = null;

    public function test_visit_photo_retry_with_same_client_uuid_is_idempotent(): void
    {
        Storage::fake('public');

        $actor = $this->actor();

        $visit = app(TenantContext::class)->withTenant(
            $actor['tenant'],
            function () use ($actor): CustomerVisit {
                $customer = Customer::create([
                    'code' => 'B12-CUS-1',
                    'name' => 'Batch 12 Customer',
                    'is_active' => true,
                ]);

                return CustomerVisit::create([
                    'user_id' => $actor['user']->id,
                    'salesman_id' => $actor['salesman']->id,
                    'device_id' => $actor['device']->id,
                    'customer_id' => $customer->id,
                    'status' => 'active',
                    'checked_in_at' => now()->subMinutes(5),
                    'checkin_latitude' => 34.5553,
                    'checkin_longitude' => 69.2075,
                    'checkin_accuracy' => 8,
                ]);
            }
        );

        $clientUuid = (string) Str::uuid();

        $first = $this->post(
            '/api/v1/visits/'.$visit->uuid.'/photos',
            [
                'client_uuid' => $clientUuid,
                'photo' => UploadedFile::fake()->image('visit.jpg', 800, 600),
                'captured_at' => now()->subMinute()->toISOString(),
            ],
            $this->headers(),
        );

        $first
            ->assertCreated()
            ->assertJsonPath('data.id', $clientUuid);

        $second = $this->post(
            '/api/v1/visits/'.$visit->uuid.'/photos',
            [
                'client_uuid' => $clientUuid,
                'photo' => UploadedFile::fake()->image('visit-retry.jpg', 800, 600),
                'captured_at' => now()->subMinute()->toISOString(),
            ],
            $this->headers(),
        );

        $second
            ->assertOk()
            ->assertJsonPath('data.id', $clientUuid);

        $this->assertDatabaseCount('visit_photos', 1);

        $photo = app(TenantContext::class)->withTenant(
            $actor['tenant'],
            fn () => \App\Models\VisitPhoto::where('uuid', $clientUuid)->firstOrFail()
        );

        Storage::disk('public')->assertExists($photo->path);
        $this->assertCount(
            1,
            Storage::disk('public')->allFiles('visits/'.$visit->uuid),
        );
    }

    private function actor(): array
    {
        $tenant = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Batch 12 Tenant',
            'slug' => 'batch-12-tenant',
            'timezone' => 'Asia/Kabul',
        ]);

        [$user, $salesman, $device] = app(TenantContext::class)
            ->withPlatformScope(function () use ($tenant): array {
                $user = User::create([
                    'uuid' => (string) Str::uuid(),
                    'tenant_id' => $tenant->id,
                    'name' => 'Batch 12 Salesman',
                    'email' => 'batch12-salesman@example.test',
                    'password' => Hash::make('password'),
                    'role' => 'salesman',
                    'is_active' => true,
                ]);

                $salesman = Salesman::create([
                    'tenant_id' => $tenant->id,
                    'user_id' => $user->id,
                    'employee_code' => 'B12-SAL-1',
                    'first_name' => 'Batch',
                    'last_name' => 'Twelve',
                    'is_active' => true,
                ]);

                $device = Device::create([
                    'tenant_id' => $tenant->id,
                    'user_id' => $user->id,
                    'salesman_id' => $salesman->id,
                    'device_uuid' => 'batch12-device',
                    'installation_uuid' => 'batch12-install',
                    'is_active' => true,
                ]);

                return [$user, $salesman, $device];
            });

        $this->token = app(TenantContext::class)->withTenant(
            $tenant,
            fn () => $user->createToken('mobile-'.$device->uuid)->plainTextToken
        );

        return compact('tenant', 'user', 'salesman', 'device');
    }

    private function headers(): array
    {
        return [
            'Authorization' => 'Bearer '.$this->token,
            'X-Device-UUID' => 'batch12-device',
            'X-Installation-UUID' => 'batch12-install',
            'X-App-Version' => '1.0',
            'X-Platform' => 'android',
            'X-OS-Version' => '16',
            'Accept' => 'application/json',
        ];
    }
}
