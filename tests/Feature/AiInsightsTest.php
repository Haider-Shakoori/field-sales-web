<?php

namespace Tests\Feature;

use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerFollowUp;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\Territory;
use App\Models\User;
use App\Services\AiConversationService;
use App\Services\AiInsightsService;
use App\Services\AiInsightToolService;
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

        $response = $this->actingAs($admin)
            ->postJson(route('admin.ai-insights.ask'), [
                'question' => 'How many follow-ups are overdue?',
            ])
            ->assertOk()
            ->assertJson([
                'answer' => 'Overdue follow-ups: 1. Open high-priority follow-ups: 1.',
                'source' => 'fieldpulse_grounded_rules',
                'provider_status' => 'local',
            ]);

        $conversationUuid = $response->json('conversation.uuid');

        $this->assertDatabaseHas('ai_conversations', [
            'tenant_id' => $tenant->id,
            'user_id' => $admin->id,
            'uuid' => $conversationUuid,
        ]);
        $this->assertSame(
            2,
            app(TenantContext::class)->withTenant(
                $tenant,
                fn () => AiMessage::count(),
            ),
        );

        $this->actingAs($admin)
            ->post(route('admin.ai-insights.ask'), [
                'question' => 'How many follow-ups are overdue?',
                'conversation_uuid' => $conversationUuid,
            ])
            ->assertRedirect(
                route('admin.ai-insights.index', [
                    'conversation' => $conversationUuid,
                ]).'#ask-fieldpulse-bottom',
            );
    }

    public function test_chat_history_can_be_archived_and_restored(): void
    {
        [$tenant, $admin] = $this->fixture();

        $conversation = app(TenantContext::class)->withTenant(
            $tenant,
            fn () => app(AiConversationService::class)->create(
                $admin,
                'Remember this conversation',
            ),
        );

        $this->actingAs($admin)
            ->delete(route(
                'admin.ai-insights.conversations.archive',
                $conversation->uuid,
            ))
            ->assertRedirect(route('admin.ai-insights.index'));

        $this->assertNotNull(
            app(TenantContext::class)->withTenant(
                $tenant,
                fn () => AiConversation::where('uuid', $conversation->uuid)
                    ->firstOrFail()
                    ->archived_at,
            ),
        );

        $this->actingAs($admin)
            ->patch(route(
                'admin.ai-insights.conversations.restore',
                $conversation->uuid,
            ))
            ->assertRedirect(route('admin.ai-insights.index', [
                'conversation' => $conversation->uuid,
            ]));

        $this->assertNull(
            app(TenantContext::class)->withTenant(
                $tenant,
                fn () => AiConversation::where('uuid', $conversation->uuid)
                    ->firstOrFail()
                    ->archived_at,
            ),
        );

        $this->actingAs($admin)
            ->get(route('admin.ai-insights.index'))
            ->assertOk()
            ->assertSee('Search conversations')
            ->assertSee('Check AI connection');
    }

    public function test_provider_health_check_verifies_groq_without_exposing_key(): void
    {
        [, $admin] = $this->fixture();

        config()->set('ai.enabled', true);
        config()->set('ai.provider', 'groq');
        config()->set('ai.base_url', null);
        config()->set('ai.api_key', 'groq-health-test-key');
        config()->set('ai.model', 'openai/gpt-oss-120b');

        Http::fake([
            'https://api.groq.com/openai/v1/models' => Http::response([
                'object' => 'list',
                'data' => [],
            ], 200),
        ]);

        $this->actingAs($admin)
            ->getJson(route('admin.ai-insights.provider-health'))
            ->assertOk()
            ->assertJson([
                'ok' => true,
                'status' => 'connected',
                'provider' => 'groq',
                'model' => 'openai/gpt-oss-120b',
            ])
            ->assertJsonMissing(['api_key' => 'groq-health-test-key']);

        Http::assertSent(fn ($request): bool => $request->url()
            === 'https://api.groq.com/openai/v1/models');
    }

    public function test_tenant_can_disable_external_ai_without_disabling_local_answers(): void
    {
        [$tenant, $admin] = $this->fixture();

        config()->set('ai.enabled', true);
        config()->set('ai.provider', 'groq');
        config()->set('ai.api_key', 'should-not-be-used');
        config()->set('ai.model', 'openai/gpt-oss-120b');

        $tenant->update([
            'settings' => [
                'ai' => [
                    'enabled' => false,
                ],
            ],
        ]);

        Http::fake();

        $result = app(TenantContext::class)->withTenant(
            $tenant,
            fn () => app(AiInsightsService::class)->answer(
                $admin,
                'How many visits happened today?',
            ),
        );

        $this->assertSame('fieldpulse_grounded_rules', $result['source']);
        $this->assertSame('local', $result['provider_status']);
        Http::assertNothingSent();
    }

    public function test_tenant_can_block_customer_data_tools_below_global_ai_policy(): void
    {
        [$tenant, $admin] = $this->fixture();

        config()->set('ai.enabled', true);
        config()->set('ai.allow_customer_data', true);

        $tenant->update([
            'settings' => [
                'ai' => [
                    'enabled' => true,
                    'allow_customer_data' => false,
                ],
            ],
        ]);

        $definitions = app(TenantContext::class)->withTenant(
            $tenant,
            fn () => app(AiInsightToolService::class)->definitions($admin),
        );
        $names = collect($definitions)->pluck('function.name')->all();

        $this->assertNotContains('search_customers', $names);
        $this->assertNotContains('get_receivables', $names);
        $this->assertContains('get_report', $names);
    }

    public function test_tenant_history_retention_prunes_only_expired_conversations(): void
    {
        [$tenant, $admin] = $this->fixture();

        $tenant->update([
            'settings' => [
                'ai' => [
                    'history_retention_days' => 30,
                ],
            ],
        ]);

        [$expired, $current, $deleted] = app(TenantContext::class)->withTenant(
            $tenant,
            function () use ($admin): array {
                $service = app(AiConversationService::class);
                $expired = $service->create($admin, 'Old AI conversation');
                $current = $service->create($admin, 'Current AI conversation');

                $expired->forceFill([
                    'last_message_at' => now()->subDays(31),
                ])->save();

                return [
                    $expired,
                    $current,
                    $service->pruneExpiredFor($admin),
                ];
            },
        );

        $this->assertSame(1, $deleted);

        app(TenantContext::class)->withTenant(
            $tenant,
            function () use ($expired, $current): void {
                $this->assertFalse(
                    AiConversation::whereKey($expired->id)->exists(),
                );
                $this->assertTrue(
                    AiConversation::whereKey($current->id)->exists(),
                );
            },
        );
    }

    public function test_groq_agent_can_call_permission_aware_fieldpulse_tools(): void
    {
        [$tenant, $admin] = $this->fixture();

        config()->set('ai.enabled', true);
        config()->set('ai.provider', 'groq');
        config()->set('ai.base_url', null);
        config()->set('ai.api_key', 'groq-test-key');
        config()->set('ai.model', 'openai/gpt-oss-120b');
        config()->set('ai.endpoint', null);
        config()->set('ai.allow_customer_data', false);

        Http::fakeSequence()
            ->push([
                'choices' => [[
                    'message' => [
                        'role' => 'assistant',
                        'content' => null,
                        'tool_calls' => [[
                            'id' => 'call_performance',
                            'type' => 'function',
                            'function' => [
                                'name' => 'get_report',
                                'arguments' => json_encode([
                                    'type' => 'performance',
                                    'date_from' => '2026-09-01',
                                    'date_to' => '2026-09-24',
                                ]),
                            ],
                        ]],
                    ],
                ]],
                'usage' => [
                    'prompt_tokens' => 120,
                    'completion_tokens' => 10,
                ],
            ], 200, ['x-request-id' => 'req-tool-round'])
            ->push([
                'choices' => [[
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'There are no active salesman performance rows for that period.',
                    ],
                ]],
                'usage' => [
                    'prompt_tokens' => 180,
                    'completion_tokens' => 30,
                ],
            ], 200, ['x-request-id' => 'req-final']);

        $result = app(TenantContext::class)->withTenant(
            $tenant,
            fn () => app(AiInsightsService::class)->answer(
                $admin,
                'Who sold the most this month?',
            ),
        );

        $this->assertSame('configured_ai_agent', $result['source']);
        $this->assertSame(
            'There are no active salesman performance rows for that period.',
            $result['answer'],
        );
        $this->assertSame(300, $result['usage']['prompt_tokens']);
        $this->assertSame(40, $result['usage']['completion_tokens']);
        $this->assertSame(1, $result['tool_call_count']);
        $this->assertSame(200, $result['provider_http_status']);
        $this->assertSame('req-final', $result['provider_request_id']);

        Http::assertSentCount(2);

        $requests = Http::recorded();
        $firstPayload = $requests[0][0]->data();
        $secondPayload = $requests[1][0]->data();

        $this->assertSame(
            'https://api.groq.com/openai/v1/chat/completions',
            $requests[0][0]->url(),
        );
        $this->assertSame('openai/gpt-oss-120b', $firstPayload['model']);
        $this->assertContains(
            'get_report',
            collect($firstPayload['tools'])
                ->pluck('function.name')
                ->all(),
        );
        $this->assertNotContains(
            'search_customers',
            collect($firstPayload['tools'])
                ->pluck('function.name')
                ->all(),
        );
        $this->assertSame(
            'tool',
            collect($secondPayload['messages'])->last()['role'],
        );
        $this->assertStringContainsString(
            'Performance report',
            collect($secondPayload['messages'])->last()['content'],
        );
    }

    public function test_conversation_history_is_sent_to_groq_for_follow_up_questions(): void
    {
        [$tenant, $admin] = $this->fixture();

        config()->set('ai.enabled', true);
        config()->set('ai.provider', 'groq');
        config()->set('ai.api_key', 'groq-test-key');
        config()->set('ai.model', 'openai/gpt-oss-120b');

        Http::fakeSequence()
            ->push([
                'choices' => [[
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'Ahmad was the top seller this month.',
                    ],
                ]],
                'usage' => [
                    'prompt_tokens' => 100,
                    'completion_tokens' => 20,
                ],
            ], 200)
            ->push([
                'choices' => [[
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'Last month, Ahmad was second.',
                    ],
                ]],
            ], 200);

        $first = $this->actingAs($admin)
            ->postJson(route('admin.ai-insights.ask'), [
                'question' => 'Who sold the most this month?',
            ])
            ->assertOk()
            ->assertJson([
                'source' => 'configured_ai_agent',
                'provider_status' => 'connected',
            ]);

        $conversationUuid = $first->json('conversation.uuid');

        $this->actingAs($admin)
            ->postJson(route('admin.ai-insights.ask'), [
                'question' => 'What about last month?',
                'conversation_uuid' => $conversationUuid,
            ])
            ->assertOk()
            ->assertJson([
                'answer' => 'Last month, Ahmad was second.',
            ]);

        $requests = Http::recorded();
        $secondMessages = $requests[1][0]->data()['messages'];

        $this->assertSame(
            ['system', 'user', 'assistant', 'user'],
            collect($secondMessages)->pluck('role')->all(),
        );
        $this->assertSame(
            'Ahmad was the top seller this month.',
            $secondMessages[2]['content'],
        );
        $this->assertSame(
            'What about last month?',
            $secondMessages[3]['content'],
        );

        $stored = app(TenantContext::class)->withTenant(
            $tenant,
            fn () => AiConversation::where('uuid', $conversationUuid)
                ->with('messages')
                ->firstOrFail(),
        );

        $this->assertCount(4, $stored->messages);
        $this->assertSame(100, $stored->messages[1]->prompt_tokens);
        $this->assertSame(20, $stored->messages[1]->completion_tokens);
    }

    public function test_groq_failure_is_visible_and_falls_back_to_local_analysis(): void
    {
        [, $admin] = $this->fixture();

        config()->set('ai.enabled', true);
        config()->set('ai.provider', 'groq');
        config()->set('ai.api_key', 'groq-test-key');
        config()->set('ai.model', 'openai/gpt-oss-120b');

        Http::fake([
            'https://api.groq.com/openai/v1/chat/completions' => Http::response([
                'error' => ['message' => 'Rate limit reached'],
            ], 429),
        ]);

        $this->actingAs($admin)
            ->postJson(route('admin.ai-insights.ask'), [
                'question' => 'How many visits happened today?',
            ])
            ->assertOk()
            ->assertJson([
                'source' => 'fieldpulse_grounded_rules',
                'provider_status' => 'fallback',
                'fallback_reason' => 'http_429',
                'fallback_message' => 'The AI provider rate limit has been reached.',
            ]);
    }

    public function test_ai_tool_catalog_expands_with_existing_user_permissions(): void
    {
        [$tenant, $admin] = $this->fixture();

        app(TenantContext::class)->withTenant($tenant, function () use ($admin): void {
            $permissions = collect([
                'catalog:view',
                'expenses:view',
                'stock:view',
                'returns:view',
            ])->map(fn (string $slug) => Permission::firstOrCreate(
                ['slug' => $slug],
                [
                    'name' => str($slug)->replace(':', ' ')->title(),
                    'group' => str($slug)->before(':')->toString(),
                ],
            ));

            $admin->roles()->firstOrFail()->permissions()->syncWithoutDetaching(
                $permissions->pluck('id'),
            );

            $definitions = app(AiInsightToolService::class)->definitions(
                $admin->fresh()->load('roles.permissions'),
            );
            $names = collect($definitions)->pluck('function.name')->all();

            $this->assertContains('get_top_products', $names);
            $this->assertContains('get_expenses', $names);
            $this->assertContains('get_salesman_stock', $names);
            $this->assertContains('get_returns', $names);
            $this->assertContains('get_scorecards', $names);
            $this->assertContains('get_recommendations', $names);
            $this->assertContains('get_manager_briefing', $names);
            $this->assertNotContains('search_customers', $names);
        });
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
                && array_key_exists('customer_names', $payload['snapshot']) === false
                && str_contains(json_encode($payload), 'Portal Customer') === false;
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
                'name' => 'AI Manager',
                'slug' => 'ai-manager',
                'is_system' => false,
            ]);
            $role->permissions()->sync($permissions->pluck('id'));

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
