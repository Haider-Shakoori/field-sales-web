<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AiConversationService;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class AiUsageAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_ai_usage_dashboard_aggregates_tokens_tools_cost_and_audit_details(): void
    {
        [$tenant, $auditor] = $this->fixture(true);

        config()->set('ai.input_cost_per_million', 0.15);
        config()->set('ai.output_cost_per_million', 0.60);

        app(TenantContext::class)->withTenant(
            $tenant,
            function () use ($auditor): void {
                $service = app(AiConversationService::class);
                $conversation = $service->create(
                    $auditor,
                    'Who sold the most this month?',
                );

                $service->appendUser(
                    $conversation,
                    $auditor,
                    'Who sold the most this month?',
                );

                $service->appendAssistant($conversation, [
                    'answer' => 'Ahmad sold the most this month.',
                    'source' => 'configured_ai_agent',
                    'provider' => 'groq',
                    'model' => 'openai/gpt-oss-120b',
                    'provider_status' => 'connected',
                    'provider_http_status' => 200,
                    'provider_request_id' => 'req-audit-123',
                    'latency_ms' => 245,
                    'usage' => [
                        'prompt_tokens' => 1000,
                        'completion_tokens' => 200,
                    ],
                    'tool_call_count' => 2,
                    'tools_used' => [
                        'get_report',
                        'get_recommendations',
                    ],
                ]);
            },
        );

        $this->actingAs($auditor)
            ->get(route('admin.ai-insights.usage'))
            ->assertOk()
            ->assertSee('AI Usage &amp; Audit', false)
            ->assertSee('Who sold the most this month?')
            ->assertSee('req-audit-123')
            ->assertSee('get_report')
            ->assertSee('get_recommendations')
            ->assertSee('1,200')
            ->assertSee('USD 0.000270');
    }

    public function test_question_level_ai_audit_requires_audit_permission(): void
    {
        [$tenant, $auditor] = $this->fixture(true);
        [, $reportViewer] = $this->fixture(false, $tenant);

        app(TenantContext::class)->withTenant(
            $tenant,
            function () use ($auditor): void {
                $service = app(AiConversationService::class);
                $conversation = $service->create(
                    $auditor,
                    'Secret customer-level audit question',
                );

                $service->appendUser(
                    $conversation,
                    $auditor,
                    'Secret customer-level audit question',
                );

                $service->appendAssistant($conversation, [
                    'answer' => 'Private answer.',
                    'source' => 'fieldpulse_grounded_rules',
                    'provider_status' => 'local',
                    'usage' => [],
                    'tools_used' => [],
                ]);
            },
        );

        $this->actingAs($reportViewer)
            ->get(route('admin.ai-insights.usage'))
            ->assertOk()
            ->assertSee('Restricted')
            ->assertDontSee('Secret customer-level audit question')
            ->assertDontSee($auditor->name);
    }

    private function fixture(
        bool $withAudit,
        ?Tenant $existingTenant = null,
    ): array {
        $context = app(TenantContext::class);

        $tenant = $existingTenant
            ?? $context->withPlatformScope(fn () => Tenant::create([
                'uuid' => (string) Str::uuid(),
                'name' => 'AI Audit Tenant',
                'slug' => 'ai-audit-'.Str::lower(Str::random(6)),
                'timezone' => 'Asia/Kabul',
                'subscription_status' => 'active',
            ]));

        return $context->withTenant(
            $tenant,
            function () use ($tenant, $withAudit): array {
                $slugs = ['reports:view'];

                if ($withAudit) {
                    $slugs[] = 'audit:view';
                }

                $permissions = collect($slugs)->map(
                    fn (string $slug) => Permission::firstOrCreate(
                        ['slug' => $slug],
                        [
                            'name' => str($slug)
                                ->replace(':', ' ')
                                ->title(),
                            'group' => str($slug)->before(':')->toString(),
                        ],
                    ),
                );

                $suffix = Str::lower(Str::random(6));
                $role = Role::create([
                    'tenant_id' => $tenant->id,
                    'name' => $withAudit ? 'AI Auditor' : 'Report Viewer',
                    'slug' => ($withAudit ? 'ai-auditor-' : 'report-viewer-')
                        .$suffix,
                    'is_system' => false,
                ]);
                $role->permissions()->sync($permissions->pluck('id'));

                $user = User::create([
                    'uuid' => (string) Str::uuid(),
                    'tenant_id' => $tenant->id,
                    'name' => $withAudit ? 'AI Auditor' : 'Report Viewer',
                    'email' => $suffix.'@ai-audit.example.test',
                    'password' => Hash::make('password'),
                    'role' => $role->slug,
                    'is_active' => true,
                ]);
                $user->syncPrimaryRole($role);

                return [$tenant, $user];
            },
        );
    }
}
