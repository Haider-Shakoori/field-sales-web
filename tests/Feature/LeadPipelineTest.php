<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Device;
use App\Models\Lead;
use App\Models\Salesman;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantProvisioningService;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class LeadPipelineTest extends TestCase
{
    use RefreshDatabase;

    private ?string $token = null;

    public function test_manager_can_capture_activity_and_convert_without_duplicate_customer(): void
    {
        $f = $this->fixture();

        $this->actingAs($f['admin'])->post(route('admin.leads.store'), [
            'name' => 'Kabul Market',
            'contact_person' => 'Amin',
            'phone' => '+93700111222',
            'source' => 'referral',
            'priority' => 'high',
            'assigned_salesman_id' => $f['salesman']->id,
            'estimated_value' => 15000,
            'currency' => 'AFN',
        ])->assertRedirect();

        $lead = app(TenantContext::class)->withTenant($f['tenant'], fn () => Lead::firstOrFail());

        $this->actingAs($f['admin'])->post(route('admin.leads.activities.store', $lead), [
            'type' => 'call',
            'notes' => 'Owner requested a proposal.',
        ])->assertRedirect();

        $existing = app(TenantContext::class)->withTenant($f['tenant'], fn () => Customer::create([
            'branch_id' => $f['branch']->id,
            'code' => 'EXIST-1',
            'name' => 'Existing Kabul Market',
            'phone' => '+93700111222',
            'created_by' => $f['admin']->id,
            'is_active' => true,
        ]));

        $this->actingAs($f['admin'])->post(route('admin.leads.convert', $lead))->assertRedirect(route('admin.customers.show', $existing));

        $lead->refresh();
        $this->assertSame('won', $lead->stage);
        $this->assertSame($existing->id, $lead->converted_customer_id);
        $this->assertDatabaseCount('customers', 1);
        $this->assertDatabaseHas('lead_activities', ['lead_id' => $lead->id, 'type' => 'conversion']);
    }

    public function test_mobile_lead_flow_is_idempotent_owned_and_convertible(): void
    {
        $f = $this->fixture();
        $uuid = (string) Str::uuid();

        $payload = [
            'offline_uuid' => $uuid,
            'name' => 'Mobile Prospect',
            'contact_person' => 'Farid',
            'phone' => '+93700999999',
            'source' => 'field',
            'priority' => 'normal',
            'estimated_value' => 250,
            'currency' => 'USD',
        ];

        $this->postJson('/api/v1/leads', $payload, $this->headers())->assertCreated()->assertJsonPath('data.id', $uuid)->assertJsonPath('data.stage', 'new');
        $this->postJson('/api/v1/leads', $payload, $this->headers())->assertOk()->assertJsonPath('data.id', $uuid);
        $this->patchJson('/api/v1/leads/'.$uuid, ['stage' => 'qualified'], $this->headers())->assertOk()->assertJsonPath('data.stage', 'qualified')->assertJsonPath('data.probability', 50);

        $activityUuid = (string) Str::uuid();
        $this->postJson('/api/v1/leads/'.$uuid.'/activities', ['offline_uuid' => $activityUuid, 'type' => 'meeting', 'notes' => 'Qualified during shop visit.'], $this->headers())->assertCreated()->assertJsonPath('data.id', $activityUuid);
        $this->postJson('/api/v1/leads/'.$uuid.'/convert', [], $this->headers())->assertOk()->assertJsonPath('data.stage', 'won')->assertJsonPath('data.converted_customer_name', 'Mobile Prospect');

        $this->getJson('/api/v1/leads', $this->headers())->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $uuid);
        $this->assertDatabaseCount('leads', 1);
        $this->assertDatabaseCount('customers', 1);
    }

    private function fixture(): array
    {
        $context = app(TenantContext::class);
        $tenant = $context->withPlatformScope(fn () => Tenant::create([
            'uuid' => (string) Str::uuid(), 'name' => 'Lead Tenant', 'slug' => 'lead-'.Str::lower(Str::random(6)), 'timezone' => 'Asia/Kabul', 'subscription_status' => 'active',
        ]));

        return $context->withTenant($tenant, function () use ($tenant): array {
            $roles = app(TenantProvisioningService::class)->provisionRbac($tenant);
            $branch = Branch::create(['name' => 'Lead Branch', 'code' => 'LEAD', 'is_active' => true]);
            $admin = User::create(['uuid' => (string) Str::uuid(), 'branch_id' => $branch->id, 'name' => 'Lead Admin', 'email' => 'lead-admin@example.test', 'password' => Hash::make('password'), 'role' => 'company_admin', 'is_active' => true]);
            $admin->syncPrimaryRole($roles['company_admin']);
            $salesmanUser = User::create(['uuid' => (string) Str::uuid(), 'branch_id' => $branch->id, 'name' => 'Lead Salesman', 'email' => 'lead-salesman@example.test', 'password' => Hash::make('password'), 'role' => 'salesman', 'is_active' => true]);
            $salesmanUser->syncPrimaryRole($roles['salesman']);
            $salesman = Salesman::create(['user_id' => $salesmanUser->id, 'employee_code' => 'LEAD-S1', 'first_name' => 'Lead', 'last_name' => 'Salesman', 'is_active' => true]);
            $device = Device::create(['user_id' => $salesmanUser->id, 'salesman_id' => $salesman->id, 'device_uuid' => 'lead-device', 'installation_uuid' => 'lead-install', 'is_active' => true]);
            $this->token = $salesmanUser->createToken('mobile-'.$device->uuid)->plainTextToken;

            return compact('tenant', 'branch', 'admin', 'salesmanUser', 'salesman', 'device');
        });
    }

    private function headers(): array
    {
        return ['Authorization' => 'Bearer '.$this->token, 'X-Device-UUID' => 'lead-device', 'X-Installation-UUID' => 'lead-install', 'X-App-Version' => '1.0', 'X-Platform' => 'android', 'X-OS-Version' => '16'];
    }
}
