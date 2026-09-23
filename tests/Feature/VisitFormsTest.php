<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Device;
use App\Models\RouteCustomer;
use App\Models\Salesman;
use App\Models\SalesmanAssignment;
use App\Models\SalesRoute;
use App\Models\Tenant;
use App\Models\Territory;
use App\Models\User;
use App\Models\VisitFormQuestion;
use App\Models\VisitFormSubmission;
use App\Models\VisitFormTemplate;
use App\Models\WorkSession;
use App\Services\TenantProvisioningService;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class VisitFormsTest extends TestCase
{
    use RefreshDatabase;

    private ?string $token = null;

    protected function setUp(): void
    {
        parent::setUp();

        CarbonImmutable::setTestNow('2026-09-23T06:30:00Z');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_create_page_renders_and_stock_navigation_is_visible(): void
    {
        $actor = $this->actor();

        $this->actingAs($actor['admin'])
            ->get('/admin/visit-forms/create')
            ->assertOk()
            ->assertSee('New visit form')
            ->assertSee(route('admin.stock.index'), false)
            ->assertSee(route('admin.returns.index'), false);
    }

    public function test_required_route_form_blocks_checkout_until_idempotent_submission(): void
    {
        $actor = $this->actor();

        $this->actingAs($actor['admin'])
            ->post('/admin/visit-forms', [
                'code' => 'RETAIL-AUDIT',
                'name' => 'Retail Visit Audit',
                'description' => 'Required retail execution check.',
                'scope_type' => 'route',
                'route_id' => $actor['route']->id,
                'required_on_checkout' => '1',
                'is_active' => '1',
                'questions' => [
                    [
                        'label' => 'Product available?',
                        'type' => 'yes_no',
                        'is_required' => '1',
                    ],
                    [
                        'label' => 'Shelf position',
                        'type' => 'single_choice',
                        'options_text' => "Top\nMiddle\nBottom",
                        'is_required' => '0',
                    ],
                ],
            ])
            ->assertRedirect();

        $template = app(TenantContext::class)->withTenant(
            $actor['tenant'],
            fn () => VisitFormTemplate::with('questions')
                ->where('code', 'RETAIL-AUDIT')
                ->firstOrFail()
        );

        $this->assertTrue($template->required_on_checkout);
        $this->assertSame(2, $template->questions->count());
        $this->assertSame(1, $template->version);

        auth()->guard('web')->logout();

        $this->createWorkSession($actor);

        $visitUuid = (string) Str::uuid();

        $this->postJson('/api/v1/visits/check-in', [
            'offline_uuid' => $visitUuid,
            'customer_id' => $actor['customer']->uuid,
            'latitude' => 34.50001,
            'longitude' => 69.20001,
            'accuracy' => 7,
            'checked_in_at' => '2026-09-23T05:00:00Z',
        ], $this->headers($actor))
            ->assertCreated()
            ->assertJsonPath('data.is_planned', true);

        $this->getJson(
            '/api/v1/visit-forms?customer_id='.$actor['customer']->uuid.'&visit_id='.$visitUuid,
            $this->headers($actor),
        )
            ->assertOk()
            ->assertJsonPath('data.templates.0.id', $template->uuid)
            ->assertJsonPath('data.templates.0.version', 1)
            ->assertJsonPath('data.templates.0.required_on_checkout', true)
            ->assertJsonPath('data.templates.0.questions.0.label', 'Product available?');

        $checkout = [
            'latitude' => 34.50002,
            'longitude' => 69.20002,
            'accuracy' => 7,
            'checked_out_at' => '2026-09-23T05:10:00Z',
            'outcome' => 'order_placed',
        ];

        $this->postJson(
            '/api/v1/visits/'.$visitUuid.'/check-out',
            $checkout,
            $this->headers($actor),
        )
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'REQUIRED_VISIT_FORM_MISSING')
            ->assertJsonPath('error.details.forms.0.id', $template->uuid);

        $submissionUuid = (string) Str::uuid();
        $yesNo = $template->questions->firstWhere('type', 'yes_no');
        $choice = $template->questions->firstWhere('type', 'single_choice');

        $submissionPayload = [
            'offline_uuid' => $submissionUuid,
            'template_id' => $template->uuid,
            'template_version' => 1,
            'submitted_at' => '2026-09-23T05:08:00Z',
            'answers' => [
                [
                    'question_id' => $yesNo->uuid,
                    'value' => true,
                ],
                [
                    'question_id' => $choice->uuid,
                    'value' => 'Middle',
                ],
            ],
        ];

        $this->postJson(
            '/api/v1/visits/'.$visitUuid.'/form-submissions',
            $submissionPayload,
            $this->headers($actor),
        )
            ->assertCreated()
            ->assertJsonPath('data.id', $submissionUuid)
            ->assertJsonPath('data.template_version', 1);

        $this->postJson(
            '/api/v1/visits/'.$visitUuid.'/form-submissions',
            $submissionPayload,
            $this->headers($actor),
        )->assertOk();

        $this->assertDatabaseCount('visit_form_submissions', 1);
        $this->assertDatabaseCount('visit_form_answers', 2);

        $this->postJson(
            '/api/v1/visits/'.$visitUuid.'/check-out',
            $checkout,
            $this->headers($actor),
        )
            ->assertOk()
            ->assertJsonPath('data.status', 'completed');
    }

    public function test_cached_old_form_version_can_sync_after_admin_updates_template(): void
    {
        $actor = $this->actor();

        $template = app(TenantContext::class)->withTenant(
            $actor['tenant'],
            function () use ($actor): VisitFormTemplate {
                $template = VisitFormTemplate::create([
                    'code' => 'OFFLINE-AUDIT',
                    'name' => 'Offline Audit',
                    'scope_type' => 'route',
                    'route_id' => $actor['route']->id,
                    'version' => 1,
                    'required_on_checkout' => false,
                    'is_active' => true,
                    'created_by' => $actor['admin']->id,
                    'updated_by' => $actor['admin']->id,
                ]);

                $template->questions()->create([
                    'label' => 'Old cached question',
                    'type' => 'text',
                    'template_version' => 1,
                    'is_active' => true,
                    'is_required' => true,
                    'sort_order' => 1,
                ]);

                return $template->fresh()->load('questions');
            }
        );

        $oldQuestion = $template->questions->first();

        $this->actingAs($actor['admin'])
            ->put('/admin/visit-forms/'.$template->id, [
                'code' => 'OFFLINE-AUDIT',
                'name' => 'Offline Audit Updated',
                'scope_type' => 'route',
                'route_id' => $actor['route']->id,
                'required_on_checkout' => '0',
                'is_active' => '1',
                'questions' => [
                    [
                        'label' => 'New current question',
                        'type' => 'yes_no',
                        'is_required' => '1',
                    ],
                ],
            ])
            ->assertRedirect();

        $template = app(TenantContext::class)->withTenant(
            $actor['tenant'],
            fn () => VisitFormTemplate::with('questions')
                ->whereKey($template->id)
                ->firstOrFail()
        );

        $this->assertSame(2, $template->version);
        $this->assertSame('New current question', $template->questions->first()->label);

        $oldQuestionRow = app(TenantContext::class)->withTenant(
            $actor['tenant'],
            fn () => VisitFormQuestion::where('uuid', $oldQuestion->uuid)->firstOrFail()
        );

        $this->assertFalse($oldQuestionRow->is_active);
        $this->assertSame(1, $oldQuestionRow->template_version);

        auth()->guard('web')->logout();

        $this->createWorkSession($actor);
        $visitUuid = (string) Str::uuid();

        $this->postJson('/api/v1/visits/check-in', [
            'offline_uuid' => $visitUuid,
            'customer_id' => $actor['customer']->uuid,
            'latitude' => 34.50001,
            'longitude' => 69.20001,
            'accuracy' => 7,
            'checked_in_at' => '2026-09-23T05:00:00Z',
        ], $this->headers($actor))->assertCreated();

        $submissionUuid = (string) Str::uuid();

        $this->postJson('/api/v1/visits/'.$visitUuid.'/form-submissions', [
            'offline_uuid' => $submissionUuid,
            'template_id' => $template->uuid,
            'template_version' => 1,
            'submitted_at' => '2026-09-23T05:05:00Z',
            'answers' => [
                [
                    'question_id' => $oldQuestion->uuid,
                    'value' => 'Captured while offline',
                ],
            ],
        ], $this->headers($actor))
            ->assertCreated()
            ->assertJsonPath('data.template_version', 1)
            ->assertJsonPath('data.answers.0.question_id', $oldQuestion->uuid);

        $submission = app(TenantContext::class)->withTenant(
            $actor['tenant'],
            fn () => VisitFormSubmission::where('uuid', $submissionUuid)->firstOrFail()
        );

        $this->assertSame(1, $submission->template_version);
    }

    public function test_invalid_choice_answer_is_rejected(): void
    {
        $actor = $this->actor();

        [$template, $question] = app(TenantContext::class)->withTenant(
            $actor['tenant'],
            function () use ($actor): array {
                $template = VisitFormTemplate::create([
                    'code' => 'CHOICE-AUDIT',
                    'name' => 'Choice Audit',
                    'scope_type' => 'all',
                    'version' => 1,
                    'is_active' => true,
                    'created_by' => $actor['admin']->id,
                    'updated_by' => $actor['admin']->id,
                ]);

                $question = $template->questions()->create([
                    'label' => 'Display quality',
                    'type' => 'single_choice',
                    'options' => ['Good', 'Average', 'Poor'],
                    'template_version' => 1,
                    'is_active' => true,
                    'is_required' => true,
                    'sort_order' => 1,
                ]);

                return [$template, $question];
            }
        );

        $this->createWorkSession($actor);
        $visitUuid = (string) Str::uuid();

        $this->postJson('/api/v1/visits/check-in', [
            'offline_uuid' => $visitUuid,
            'customer_id' => $actor['customer']->uuid,
            'latitude' => 34.50001,
            'longitude' => 69.20001,
            'accuracy' => 7,
            'checked_in_at' => '2026-09-23T05:00:00Z',
        ], $this->headers($actor))->assertCreated();

        $this->postJson('/api/v1/visits/'.$visitUuid.'/form-submissions', [
            'offline_uuid' => (string) Str::uuid(),
            'template_id' => $template->uuid,
            'template_version' => 1,
            'answers' => [
                [
                    'question_id' => $question->uuid,
                    'value' => 'Excellent',
                ],
            ],
        ], $this->headers($actor))
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonFragment([
                'Display quality must be one of the configured options.',
            ]);
    }

    private function actor(): array
    {
        $context = app(TenantContext::class);

        $tenant = $context->withPlatformScope(fn () => Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Visit Forms Tenant',
            'slug' => 'visit-forms-'.Str::lower(Str::random(6)),
            'timezone' => 'Asia/Kabul',
            'subscription_status' => 'active',
        ]));

        [$admin, $salesUser, $salesman, $device, $branch, $territory, $route, $customer] = $context
            ->withTenant($tenant, function () use ($tenant): array {
                $branch = Branch::create([
                    'code' => 'KBL',
                    'name' => 'Kabul Main',
                    'is_active' => true,
                ]);

                $territory = Territory::create([
                    'branch_id' => $branch->id,
                    'code' => 'KBL-C',
                    'name' => 'Kabul Central',
                    'is_active' => true,
                ]);

                $route = SalesRoute::create([
                    'branch_id' => $branch->id,
                    'territory_id' => $territory->id,
                    'code' => 'VF-ROUTE',
                    'name' => 'Visit Form Route',
                    'weekdays' => ['wed'],
                    'is_active' => true,
                ]);

                $admin = User::create([
                    'uuid' => (string) Str::uuid(),
                    'branch_id' => $branch->id,
                    'name' => 'Forms Admin',
                    'email' => 'forms-admin@example.test',
                    'password' => Hash::make('password'),
                    'role' => 'company_admin',
                    'is_active' => true,
                ]);

                $salesUser = User::create([
                    'uuid' => (string) Str::uuid(),
                    'branch_id' => $branch->id,
                    'name' => 'Forms Salesman',
                    'email' => 'forms-salesman@example.test',
                    'password' => Hash::make('password'),
                    'role' => 'salesman',
                    'is_active' => true,
                ]);

                $roles = app(TenantProvisioningService::class)->provisionRbac($tenant);
                $admin->syncPrimaryRole($roles['company_admin']);
                $salesUser->syncPrimaryRole($roles['salesman']);

                $salesman = Salesman::create([
                    'user_id' => $salesUser->id,
                    'employee_code' => 'VF-001',
                    'first_name' => 'Forms',
                    'last_name' => 'Salesman',
                    'is_active' => true,
                ]);

                $device = Device::create([
                    'user_id' => $salesUser->id,
                    'salesman_id' => $salesman->id,
                    'device_uuid' => 'visit-forms-device',
                    'installation_uuid' => 'visit-forms-install',
                    'is_active' => true,
                ]);

                $customer = Customer::create([
                    'branch_id' => $branch->id,
                    'territory_id' => $territory->id,
                    'code' => 'VF-CUS-001',
                    'name' => 'Visit Forms Shop',
                    'latitude' => 34.5,
                    'longitude' => 69.2,
                    'geofence_radius_meters' => 100,
                    'is_active' => true,
                ]);

                RouteCustomer::create([
                    'route_id' => $route->id,
                    'customer_id' => $customer->id,
                    'sequence_number' => 1,
                    'planned_visit_minutes' => 10,
                ]);

                SalesmanAssignment::create([
                    'salesman_id' => $salesman->id,
                    'branch_id' => $branch->id,
                    'territory_id' => $territory->id,
                    'route_id' => $route->id,
                    'effective_from' => '2026-09-01',
                    'created_by' => $admin->id,
                ]);

                return [
                    $admin,
                    $salesUser,
                    $salesman,
                    $device,
                    $branch,
                    $territory,
                    $route,
                    $customer,
                ];
            });

        $this->token = $context->withTenant(
            $tenant,
            fn () => $salesUser
                ->createToken('mobile-'.$device->uuid)
                ->plainTextToken
        );

        return compact(
            'tenant',
            'admin',
            'salesUser',
            'salesman',
            'device',
            'branch',
            'territory',
            'route',
            'customer',
        );
    }

    private function createWorkSession(array $actor): void
    {
        app(TenantContext::class)->withTenant(
            $actor['tenant'],
            fn () => WorkSession::create([
                'uuid' => (string) Str::uuid(),
                'user_id' => $actor['salesUser']->id,
                'salesman_id' => $actor['salesman']->id,
                'device_id' => $actor['device']->id,
                'date' => '2026-09-23',
                'start_time' => '2026-09-23 04:00:00',
                'end_time' => '2026-09-23 10:00:00',
                'start_latitude' => 34.5,
                'start_longitude' => 69.2,
                'start_accuracy' => 5,
                'status' => 'completed',
            ])
        );
    }

    private function headers(array $actor): array
    {
        return [
            'Authorization' => 'Bearer '.$this->token,
            'X-Device-UUID' => $actor['device']->device_uuid,
            'X-Installation-UUID' => $actor['device']->installation_uuid,
            'X-App-Version' => '1.0',
            'X-Platform' => 'android',
            'X-OS-Version' => '16',
        ];
    }
}
