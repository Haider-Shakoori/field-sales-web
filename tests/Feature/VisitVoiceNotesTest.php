<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerVisit;
use App\Models\Device;
use App\Models\Salesman;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkSession;
use App\Services\TenantProvisioningService;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class VisitVoiceNotesTest extends TestCase
{
    use RefreshDatabase;

    public function test_salesman_can_upload_voice_note_idempotently_for_owned_visit(): void
    {
        Storage::fake('public');
        [$tenant, $salesmanUser, $salesman, $device, $visit, $token] = $this->fixture();
        $uuid = (string) Str::uuid();
        $headers = [
            'Authorization' => 'Bearer '.$token,
            'X-Device-UUID' => $device->device_uuid,
            'X-Installation-UUID' => $device->installation_uuid,
            'X-App-Version' => '1.0',
            'X-Platform' => 'android',
            'X-OS-Version' => '16',
        ];

        $this->post('/api/v1/visits/'.$visit->uuid.'/voice-notes', [
            'client_uuid' => $uuid,
            'audio' => UploadedFile::fake()->create('visit-note.m4a', 64, 'audio/mp4'),
            'captured_at' => now()->toISOString(),
            'duration_seconds' => 14,
        ], $headers)->assertCreated()->assertJsonPath('data.id', $uuid);

        $this->postJson('/api/v1/visits/'.$visit->uuid.'/voice-notes', [
            'client_uuid' => $uuid,
        ], $headers)->assertOk()->assertJsonPath('data.id', $uuid);

        app(TenantContext::class)->withTenant($tenant, function () use ($uuid): void {
            $this->assertDatabaseCount('visit_voice_notes', 1);
            $this->assertDatabaseHas('visit_voice_notes', ['uuid' => $uuid, 'duration_seconds' => 14]);
        });
    }

    private function fixture(): array
    {
        $context = app(TenantContext::class);
        $tenant = $context->withPlatformScope(fn () => Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Voice Tenant',
            'slug' => 'voice-'.Str::lower(Str::random(6)),
            'timezone' => 'Asia/Kabul',
            'subscription_status' => 'active',
        ]));

        return $context->withTenant($tenant, function () use ($tenant): array {
            $roles = app(TenantProvisioningService::class)->provisionRbac($tenant);
            $branch = Branch::create(['code' => 'VOICE', 'name' => 'Voice Branch', 'is_active' => true]);
            $user = User::create([
                'uuid' => (string) Str::uuid(), 'branch_id' => $branch->id, 'name' => 'Voice Salesman',
                'email' => 'voice-salesman@example.test', 'password' => Hash::make('password'), 'role' => 'salesman', 'is_active' => true,
            ]);
            $user->syncPrimaryRole($roles['salesman']);
            $salesman = Salesman::create(['user_id' => $user->id, 'employee_code' => 'VOICE-S1', 'first_name' => 'Voice', 'last_name' => 'Salesman', 'is_active' => true]);
            $device = Device::create(['user_id' => $user->id, 'salesman_id' => $salesman->id, 'device_uuid' => 'voice-device', 'installation_uuid' => 'voice-install', 'is_active' => true]);
            $customer = Customer::create(['branch_id' => $branch->id, 'code' => 'VOICE-C1', 'name' => 'Voice Customer', 'created_by' => $user->id, 'is_active' => true]);
            $session = WorkSession::create([
                'uuid' => (string) Str::uuid(),
                'user_id' => $user->id,
                'salesman_id' => $salesman->id,
                'device_id' => $device->id,
                'date' => today(),
                'start_time' => now()->subHour(),
                'start_latitude' => 34.5553,
                'start_longitude' => 69.2075,
                'start_accuracy' => 8,
                'status' => 'active',
            ]);
            $visit = CustomerVisit::create([
                'user_id' => $user->id, 'salesman_id' => $salesman->id, 'device_id' => $device->id, 'customer_id' => $customer->id,
                'work_session_id' => $session->id, 'is_planned' => false, 'status' => 'active', 'checked_in_at' => now()->subMinutes(10),
                'checkin_latitude' => 34.5553, 'checkin_longitude' => 69.2075, 'checkin_accuracy' => 8,
            ]);
            $token = $user->createToken('mobile-'.$device->uuid)->plainTextToken;
            return [$tenant, $user, $salesman, $device, $visit, $token];
        });
    }
}