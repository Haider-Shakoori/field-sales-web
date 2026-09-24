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
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrganizationController extends Controller
{
    public function edit(
        Request $request,
        AiPolicyService $aiPolicy,
    ): View {
        $tenant = $request->user()->tenant;

        return view('admin.organization.edit', [
            'tenant' => $tenant,
            'timezones' => $this->timezones(),
            'aiPolicy' => $aiPolicy->settingsFor($tenant),
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
    ): RedirectResponse {
        $tenant = $request->user()->tenant;

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'timezone' => ['required', 'timezone'],
            'contact_email' => ['nullable', 'email', 'max:191'],
            'ai_enabled' => ['required', 'boolean'],
            'ai_allow_customer_data' => ['required', 'boolean'],
            'ai_history_retention_days' => ['required', 'integer', 'in:0,30,60,90,180,365'],
        ]);

        $old = $tenant->only(['name', 'timezone', 'contact_email', 'settings']);
        $settings = $tenant->settings ?? [];
        data_set($settings, 'ai.enabled', (bool) $validated['ai_enabled']);
        data_set(
            $settings,
            'ai.allow_customer_data',
            (bool) $validated['ai_allow_customer_data'],
        );
        data_set(
            $settings,
            'ai.history_retention_days',
            (int) $validated['ai_history_retention_days'],
        );

        $tenant->update([
            'name' => $validated['name'],
            'timezone' => $validated['timezone'],
            'contact_email' => $validated['contact_email'] ?? null,
            'settings' => $settings,
        ]);

        $audit->record('organization.profile_updated', $tenant, $old, [
            'name' => $tenant->name,
            'timezone' => $tenant->timezone,
            'contact_email' => $tenant->contact_email,
            'settings' => $tenant->settings,
            'effective_ai_policy' => $aiPolicy->settingsFor($tenant->fresh()),
        ]);

        return back()->with('status', 'Organization profile updated.');
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
