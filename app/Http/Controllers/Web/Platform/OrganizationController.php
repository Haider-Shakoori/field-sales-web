<?php

namespace App\Http\Controllers\Web\Platform;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\AuditLogger;
use App\Services\BusinessOsIntegrationPolicyService;
use App\Services\TenantProvisioningService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OrganizationController extends Controller
{
    private const STATUSES = ['active', 'suspended'];

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));
        $status = (string) $request->query('status', '');

        $organizations = Tenant::query()
            ->withCount(['users', 'branches', 'salesmen', 'customers'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($nested) use ($search): void {
                    $nested->where('name', 'like', '%'.$search.'%')
                        ->orWhere('slug', 'like', '%'.$search.'%')
                        ->orWhere('contact_email', 'like', '%'.$search.'%');
                });
            })
            ->when(in_array($status, self::STATUSES, true), fn ($query) => $query->where('subscription_status', $status))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.organizations.index', [
            'organizations' => $organizations,
            'search' => $search,
            'status' => $status,
            'summary' => [
                'total' => Tenant::count(),
                'active' => Tenant::where('subscription_status', 'active')->count(),
                'suspended' => Tenant::where('subscription_status', 'suspended')->count(),
            ],
        ]);
    }

    public function create(): View
    {
        return view('admin.organizations.create', [
            'timezones' => $this->timezones(),
        ]);
    }

    public function store(
        Request $request,
        TenantProvisioningService $provisioning,
        AuditLogger $audit,
    ): RedirectResponse {
        $validated = $request->validate(array_merge($this->rules(), [
            'admin_name' => ['required', 'string', 'max:120'],
            'admin_email' => ['required', 'email', 'max:191'],
            'admin_password' => ['required', 'string', 'min:8', 'max:72'],
        ]));

        $tenant = $provisioning->provision(
            [
                'name' => $validated['name'],
                'slug' => strtolower($validated['slug']),
                'timezone' => $validated['timezone'],
                'contact_email' => $validated['contact_email'] ?? null,
            ],
            [
                'name' => $validated['admin_name'],
                'email' => strtolower($validated['admin_email']),
                'password' => $validated['admin_password'],
            ],
        );

        $audit->record('organization.created', $tenant, [], [
            'name' => $tenant->name,
            'slug' => $tenant->slug,
            'admin_email' => $validated['admin_email'],
        ], $tenant->id);

        return redirect()
            ->route('admin.organizations.index')
            ->with('status', $tenant->name.' was created with default roles and tracking policy.');
    }

    public function edit(
        Tenant $organization,
        BusinessOsIntegrationPolicyService $businessOs,
    ): View {
        return view('admin.organizations.edit', [
            'organization' => $organization,
            'timezones' => $this->timezones(),
            'businessOsPolicy' => $businessOs->settingsFor($organization),
        ]);
    }

    public function update(
        Request $request,
        Tenant $organization,
        AuditLogger $audit,
    ): RedirectResponse {
        $validated = $request->validate(array_merge(
            $this->rules($organization->id),
            [
                'businessos_platform_enabled' => [
                    'sometimes',
                    'boolean',
                ],
            ],
        ));

        $old = $organization->only([
            'name',
            'slug',
            'timezone',
            'contact_email',
            'settings',
        ]);
        $settings = $organization->settings ?? [];

        if (array_key_exists('businessos_platform_enabled', $validated)) {
            data_set(
                $settings,
                'businessos.platform_enabled',
                (bool) $validated['businessos_platform_enabled'],
            );
        }

        $organization->update([
            'name' => $validated['name'],
            'slug' => strtolower($validated['slug']),
            'timezone' => $validated['timezone'],
            'contact_email' => $validated['contact_email'] ?? null,
            'settings' => $settings,
        ]);

        $audit->record('organization.updated', $organization, $old, [
            'name' => $organization->name,
            'slug' => $organization->slug,
            'timezone' => $organization->timezone,
            'contact_email' => $organization->contact_email,
            'settings' => $organization->settings,
        ], $organization->id);

        return redirect()
            ->route('admin.organizations.index')
            ->with('status', $organization->name.' was updated.');
    }

    public function updateStatus(
        Request $request,
        Tenant $organization,
        AuditLogger $audit,
    ): RedirectResponse {
        $validated = $request->validate([
            'status' => ['required', Rule::in(self::STATUSES)],
        ]);

        $old = $organization->subscription_status;
        $organization->update(['subscription_status' => $validated['status']]);

        $audit->record('organization.status_changed', $organization, [
            'subscription_status' => $old,
        ], [
            'subscription_status' => $validated['status'],
        ], $organization->id);

        return back()->with(
            'status',
            $organization->name.' is now '.$validated['status'].'.'
        );
    }

    private function rules(?int $ignoreTenantId = null): array
    {
        $slugRule = Rule::unique('tenants', 'slug');

        if ($ignoreTenantId !== null) {
            $slugRule->ignore($ignoreTenantId);
        }

        return [
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['required', 'string', 'max:60', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slugRule],
            'timezone' => ['required', 'timezone'],
            'contact_email' => ['nullable', 'email', 'max:191'],
        ];
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
