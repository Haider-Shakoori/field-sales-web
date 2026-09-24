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
use App\Services\AiInsightsService;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class AiInsightsTest extends TestCase
{
    use RefreshDatabase;

    public function test_ai_insights_page_surfaces_grounded_operational_recommendations(): void
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

        $this->actingAs($admin)
            ->get(route('admin.ai-insights.index'))
            ->assertOk()
            ->assertSee('AI insights')
            ->assertSee('Clear overdue follow-ups')
            ->assertSee('Recover stale customer coverage')
            ->assertSee('Grounded local mode');

        $this->actingAs($admin)
            ->postJson(route('admin.ai-insights.ask'), [
                'question' => 'How many follow-ups are overdue?',
            ])
            ->assertOk()
            ->assertJson([
                'answer' => 'Overdue follow-ups: 1. Open high-priority follow-ups: 1.',
                'source' => 'fieldpulse_grounded_rules',
            ]);

        $this->actingAs($admin)
            ->post(route('admin.ai-insights.ask'), [
                'question' => 'How many follow-ups are overdue?',
            ])
            ->assertRedirect(route('admin.ai-insights.index').'#ask-fieldpulse-answer')
            ->assertSessionHas('ai_answer')
            ->assertSessionHas('ai_question', 'How many follow-ups are overdue?');
    }

    public function test_configured_ai_provider_receives_aggregate_snapshot_only(): void
    {
        [$tenant, $admin] = $this->fixture();

        config()->set('ai.enabled', true);
        config()->set('ai.endpoint', 'https://ai.example.test/answer');
        config()->set('ai.bearer_token', 'secret');
        config()->set('ai.model', 'fieldpulse-test');

        Http::fake([
            'https://ai.example.test/answer' => Http::response([
                'answer' => 'Field coverage is the main operational priority today.',
            ]),
        ]);

        $result = app(TenantContext::class)->withTenant(
            $tenant,
            fn () => app(AiInsightsService::class)->answer(
                $admin,
                'What should management focus on?',
            ),
        );

        $this->assertSame('configured_ai_provider', $result['source']);
        $this->assertSame(
            'Field coverage is the main operational priority today.',
            $result['answer'],
        );

        Http::assertSent(function ($request): bool {
            $payload = $request->data();

            return $request->url() === 'https://ai.example.test/answer'
                && $payload['model'] === 'fieldpulse-test'
                && isset($payload['snapshot']['active_customers'])
                && ! isset($payload['snapshot']['customer_names'])
                && ! str_contains(json_encode($payload), 'Portal Customer');
        });
    }

    private function fixture(): array
    {
        $context = app(TenantContext::class);

        $tenant = $context->withPlatformScope(fn () => Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'AI Tenant',
            'slug' => 'ai-'.Str::lower(Str::random(6)),
            'timezone' => 'Asia/Kabul',
            'subscription_status' => 'active',
        ]));

        return $context->withTenant($tenant, function () use ($tenant): array {
            $permission = Permission::firstOrCreate(
                ['slug' => 'reports:view'],
                [
                    'name' => 'Reports View',
                    'group' => 'reports',
                ],
            );

            $role = Role::create([
                'tenant_id' => $tenant->id,
                'name' => 'AI Manager',
                'slug' => 'ai-manager',
                'is_system' => false,
            ]);
            $role->permissions()->sync([$permission->id]);

            $admin = User::create([
                'uuid' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'name' => 'AI Manager',
                'email' => 'ai-manager@example.test',
                'password' => Hash::make('password'),
                'role' => 'ai-manager',
                'is_active' => true,
            ]);
            $admin->syncPrimaryRole($role);

            $branch = Branch::create([
                'name' => 'Kabul AI',
                'code' => 'KBL-AI',
                'is_active' => true,
            ]);

            $territory = Territory::create([
                'branch_id' => $branch->id,
                'code' => 'KBL-AI-T',
                'name' => 'AI Territory',
                'is_active' => true,
            ]);

            $customer = Customer::create([
                'branch_id' => $branch->id,
                'territory_id' => $territory->id,
                'code' => 'AI-001',
                'name' => 'Portal Customer',
                'phone' => '+93700000001',
                'credit_currency' => 'AFN',
                'credit_terms_days' => 30,
                'created_by' => $admin->id,
                'is_active' => true,
            ]);

            return [$tenant, $admin, $customer];
        });
    }
}
