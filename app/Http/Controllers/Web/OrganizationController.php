<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerVisit;
use App\Models\Order;
use App\Models\Salesman;
use App\Models\User;
use App\Services\AiPolicyService;
use App\Services\AuditLogger;
use App\Services\BusinessOsIntegrationPolicyService;
use App\Services\FieldIntelligenceSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrganizationController extends Controller
{
    public function edit(
        Request $request,
        AiPolicyService $aiPolicy,
        FieldIntelligenceSettingsService $intelligence,
        BusinessOsIntegrationPolicyService $businessOs,
    ): View {
        $tenant = $request->user()->tenant;

        return view('admin.organization.edit', [
            'tenant' => $tenant,
            'timezones' => $this->timezones(),
            'aiPolicy' => $aiPolicy->settingsFor($tenant),
            'intelligenceSettings' => $intelligence->settingsFor($tenant),
            'businessOsSettings' => $businessOs->settingsFor($tenant),
            'stats' => [
                'users' => User::count(),
                'branches' => Branch::count(),
                'salesmen' => Salesman::count(),
                'customers' => Customer::count(),
                'orders' => Order::count(),
                'visits' => CustomerVisit::count(),
            ],
        ]);
    }

    public function update(
        Request $request,
        AuditLogger $audit,
        AiPolicyService $aiPolicy,
        FieldIntelligenceSettingsService $intelligence,
        BusinessOsIntegrationPolicyService $businessOs,
    ): RedirectResponse {
        $tenant = $request->user()->tenant;

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'timezone' => ['required', 'timezone'],
            'contact_email' => ['nullable', 'email', 'max:191'],

            'ai_enabled' => ['sometimes', 'boolean'],
            'ai_allow_customer_data' => ['sometimes', 'boolean'],
            'ai_history_retention_days' => [
                'sometimes',
                'integer',
                'in:0,30,60,90,180,365',
            ],

            'smart_routes_enabled' => ['sometimes', 'boolean'],
            'route_nearby_radius_km' => ['sometimes', 'numeric', 'between:0.5,25'],
            'route_max_opportunities' => ['sometimes', 'integer', 'between:0,10'],
            'route_average_speed_kph' => ['sometimes', 'numeric', 'between:5,100'],
            'route_time_buffer_minutes' => ['sometimes', 'integer', 'between:0,180'],
            'route_enforce_workday_capacity' => ['sometimes', 'boolean'],

            'territory_auto_assign_enabled' => ['sometimes', 'boolean'],
            'territory_heat_map_enabled' => ['sometimes', 'boolean'],
            'territory_under_covered_threshold_percent' => [
                'sometimes',
                'integer',
                'between:1,100',
            ],
            'territory_stale_customer_days' => ['sometimes', 'integer', 'between:7,180'],
            'territory_stale_attention_percent' => ['sometimes', 'integer', 'between:1,100'],
            'territory_geometry_audit_enabled' => ['sometimes', 'boolean'],
            'management_route_progress_tolerance_percent' => [
                'sometimes',
                'integer',
                'between:0,50',
            ],
            'management_target_attention_percent' => [
                'sometimes',
                'integer',
                'between:1,100',
            ],
            'gamification_enabled' => ['sometimes', 'boolean'],

            'businessos_organization_key' => ['nullable', 'string', 'max:120'],
            'businessos_pull_products' => ['sometimes', 'boolean'],
            'businessos_pull_customers' => ['sometimes', 'boolean'],
            'businessos_pull_prices' => ['sometimes', 'boolean'],
            'businessos_push_orders' => ['sometimes', 'boolean'],
            'businessos_push_collections' => ['sometimes', 'boolean'],
            'businessos_push_field_customers' => ['sometimes', 'boolean'],
            'businessos_sync_interval_minutes' => [
                'sometimes',
                'integer',
                'in:5,15,30,60,120,240',
            ],
        ]);

        $old = $tenant->only(['name', 'timezone', 'contact_email', 'settings']);
        $settings = $tenant->settings ?? [];

        $this->setBoolean(
            $settings,
            'ai.enabled',
            $validated,
            'ai_enabled',
        );
        $this->setBoolean(
            $settings,
            'ai.allow_customer_data',
            $validated,
            'ai_allow_customer_data',
        );

        if (array_key_exists('ai_history_retention_days', $validated)) {
            data_set(
                $settings,
                'ai.history_retention_days',
                (int) $validated['ai_history_retention_days'],
            );
        }

        $this->setBoolean(
            $settings,
            'intelligence.smart_routes.enabled',
            $validated,
            'smart_routes_enabled',
        );

        if (array_key_exists('route_nearby_radius_km', $validated)) {
            data_set(
                $settings,
                'intelligence.smart_routes.nearby_radius_km',
                (float) $validated['route_nearby_radius_km'],
            );
        }

        if (array_key_exists('route_max_opportunities', $validated)) {
            data_set(
                $settings,
                'intelligence.smart_routes.max_opportunities',
                (int) $validated['route_max_opportunities'],
            );
        }

        if (array_key_exists('route_average_speed_kph', $validated)) {
            data_set(
                $settings,
                'intelligence.smart_routes.average_speed_kph',
                (float) $validated['route_average_speed_kph'],
            );
        }

        if (array_key_exists('route_time_buffer_minutes', $validated)) {
            data_set(
                $settings,
                'intelligence.smart_routes.time_buffer_minutes',
                (int) $validated['route_time_buffer_minutes'],
            );
        }

        $this->setBoolean(
            $settings,
            'intelligence.smart_routes.enforce_workday_capacity',
            $validated,
            'route_enforce_workday_capacity',
        );

        $this->setBoolean(
            $settings,
            'intelligence.territories.auto_assign_customers',
            $validated,
            'territory_auto_assign_enabled',
        );
        $this->setBoolean(
            $settings,
            'intelligence.territories.heat_map_enabled',
            $validated,
            'territory_heat_map_enabled',
        );

        if (array_key_exists(
            'territory_under_covered_threshold_percent',
            $validated,
        )) {
            data_set(
                $settings,
                'intelligence.territories.under_covered_threshold_percent',
                (int) $validated['territory_under_covered_threshold_percent'],
            );
        }

        if (array_key_exists('territory_stale_customer_days', $validated)) {
            data_set(
                $settings,
                'intelligence.territories.stale_customer_days',
                (int) $validated['territory_stale_customer_days'],
            );
        }

        if (array_key_exists('territory_stale_attention_percent', $validated)) {
            data_set(
                $settings,
                'intelligence.territories.stale_attention_percent',
                (int) $validated['territory_stale_attention_percent'],
            );
        }

        $this->setBoolean(
            $settings,
            'intelligence.territories.geometry_audit_enabled',
            $validated,
            'territory_geometry_audit_enabled',
        );
        if (array_key_exists(
            'management_route_progress_tolerance_percent',
            $validated,
        )) {
            data_set(
                $settings,
                'intelligence.management.route_progress_tolerance_percent',
                (int) $validated['management_route_progress_tolerance_percent'],
            );
        }

        if (array_key_exists(
            'management_target_attention_percent',
            $validated,
        )) {
            data_set(
                $settings,
                'intelligence.management.target_attention_percent',
                (int) $validated['management_target_attention_percent'],
            );
        }

        $this->setBoolean(
            $settings,
            'engagement.gamification.enabled',
            $validated,
            'gamification_enabled',
        );

        if (array_key_exists('businessos_organization_key', $validated)) {
            data_set(
                $settings,
                'businessos.organization_key',
                trim((string) ($validated['businessos_organization_key'] ?? '')),
            );
        }

        foreach ([
            'pull_products',
            'pull_customers',
            'pull_prices',
            'push_orders',
            'push_collections',
            'push_field_customers',
        ] as $key) {
            $this->setBoolean(
                $settings,
                'businessos.sync.'.$key,
                $validated,
                'businessos_'.$key,
            );
        }

        if (array_key_exists('businessos_sync_interval_minutes', $validated)) {
            data_set(
                $settings,
                'businessos.sync.interval_minutes',
                (int) $validated['businessos_sync_interval_minutes'],
            );
        }

        $tenant->update([
            'name' => $validated['name'],
            'timezone' => $validated['timezone'],
            'contact_email' => $validated['contact_email'] ?? null,
            'settings' => $settings,
        ]);

        $fresh = $tenant->fresh();

        $audit->record('organization.profile_updated', $tenant, $old, [
            'name' => $tenant->name,
            'timezone' => $tenant->timezone,
            'contact_email' => $tenant->contact_email,
            'settings' => $tenant->settings,
            'effective_ai_policy' => $aiPolicy->settingsFor($fresh),
            'effective_intelligence_settings' => $intelligence->settingsFor($fresh),
            'effective_businessos_policy' => $businessOs->settingsFor($fresh),
        ]);

        return back()->with('status', 'Organization profile updated.');
    }

    private function setBoolean(
        array &$settings,
        string $path,
        array $validated,
        string $input,
    ): void {
        if (! array_key_exists($input, $validated)) {
            return;
        }

        data_set($settings, $path, (bool) $validated[$input]);
    }

    private function timezones(): array
    {
        return [
            'UTC',
            'Asia/Kabul',
            'Asia/Karachi',
            'Asia/Dubai',
            'Asia/Tehran',
            'Asia/Kolkata',
            'Europe/London',
            'Europe/Berlin',
            'America/New_York',
            'America/Los_Angeles',
        ];
    }
}
