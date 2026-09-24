<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerFollowUp;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\Territory;
use App\Models\User;
use App\Services\AiRecommendationService;
use App\Services\ManagerBriefingService;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class AiRecommendationBriefingTest extends TestCase
{
    use RefreshDatabase;

    public function test_grounded_recommendations_are_ranked_and_reused_by_briefing(): void
    {
        [$tenant, $admin, $customer] = $this->fixture();

        app(TenantContext::class)->withTenant(
            $tenant,
            fn () => CustomerFollowUp::create([
                'customer_id' => $customer->id,
                'type' => 'payment',
                'priority' => 'high',
                'status' => 'pending',
                'due_at' => now()->subHour(),
                'notes' => 'Collect overdue account.',
                'created_by' => $admin->id,
            ]),
        );

        $recommendations = app(TenantContext::class)->withTenant(
            $tenant,
            fn () => app(AiRecommendationService::class)->build($admin, 10),
        );

        $this->assertNotEmpty($recommendations);
        $this->assertSame('overdue_followups', $recommendations[0]['id']);
        $this->assertSame('high', $recommendations[0]['severity']);
        $this->assertContains(
            'stale_customer_coverage',
            collect($recommendations)->pluck('id')->all(),
        );

        $briefing = app(TenantContext::class)->withTenant(
            $tenant,
            fn () => app(ManagerBriefingService::class)->build($admin),
        );

        $this->assertSame(
            $recommendations[0]['id'],
            $briefing['priorities'][0]['id'],
        );
        $this->assertSame(1, $briefing['exceptions']['overdue_followups']);
        $this->assertArrayHasKey('yesterday_sales', $briefing);
        $this->assertArrayHasKey('today_attendance', $briefing);
    }

    public function test_manager_can_open_morning_briefing_page(): void
    {
        [, $admin] = $this->fixture();

        $this->actingAs($admin)
            ->get(route('admin.ai-insights.briefing'))
            ->assertOk()
            ->assertSee('Manager Morning Briefing')
            ->assertSee('Yesterday commercial results')
            ->assertSee('Today priorities');
    }

    private function fixture(): array
    {
        $context = app(TenantContext::class);

        $tenant = $context->withPlatformScope(fn () => Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Briefing Tenant',
            'slug' => 'briefing-'.Str::lower(Str::random(6)),
            'timezone' => 'Asia/Kabul',
            'subscription_status' => 'active',
        ]));

        return $context->withTenant($tenant, function () use ($tenant): array {
            $permissions = collect([
                'reports:view',
                'customers:view',
            ])->map(fn (string $slug) => Permission::firstOrCreate(
                ['slug' => $slug],
                [
                    'name' => str($slug)->replace(':', ' ')->title(),
                    'group' => str($slug)->before(':')->toString(),
                ],
            ));

            $role = Role::create([
                'tenant_id' => $tenant->id,
                'name' => 'Briefing Manager',
                'slug' => 'briefing-manager',
                'is_system' => false,
            ]);
            $role->permissions()->sync($permissions->pluck('id'));

            $admin = User::create([
                'uuid' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'name' => 'Briefing Manager',
                'email' => 'briefing-manager@example.test',
                'password' => Hash::make('password'),
                'role' => 'briefing-manager',
                'is_active' => true,
            ]);
            $admin->syncPrimaryRole($role);

            $branch = Branch::create([
                'name' => 'Kabul Briefing',
                'code' => 'KBL-BRF',
                'is_active' => true,
            ]);

            $territory = Territory::create([
                'branch_id' => $branch->id,
                'code' => 'KBL-BRF-T',
                'name' => 'Briefing Territory',
                'is_active' => true,
            ]);

            $customer = Customer::create([
                'branch_id' => $branch->id,
                'territory_id' => $territory->id,
                'code' => 'BRF-001',
                'name' => 'Briefing Customer',
                'phone' => '+93700000111',
                'credit_currency' => 'AFN',
                'credit_terms_days' => 30,
                'created_by' => $admin->id,
                'is_active' => true,
            ]);

            return [$tenant, $admin, $customer];
        });
    }
}
