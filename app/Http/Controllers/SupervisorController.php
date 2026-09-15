<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSupervisorRequest;
use App\Http\Requests\UpdateSupervisorRequest;
use App\Models\Supervisor;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\FieldProfiles\EmployeeCodeGenerator;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupervisorController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Supervisor::class);

        $query = Supervisor::query()->with(['tenant', 'user']);

        if (TenantContext::hasContext()) {
            $query->where('tenant_id', TenantContext::currentId());
        }

        if ($search = $request->string('search')->trim()->toString()) {
            $query->where(fn ($q) => $q
                ->where('employee_code', 'like', "%{$search}%")
                ->orWhere('first_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%"));
        }

        if ($status = $request->string('status')->toString()) {
            $query->where('is_active', $status === 'active');
        }

        $supervisors = $query->orderBy('first_name')->paginate(20)->withQueryString();

        return view('pages.supervisors.index', compact('supervisors'));
    }

    public function create(): View
    {
        $this->authorize('create', Supervisor::class);

        $users = $this->assignableUsers();

        return view('pages.supervisors.create', compact('users'));
    }

    public function store(StoreSupervisorRequest $request): RedirectResponse
    {
        $tenantId = TenantContext::currentId();

        abort_if($tenantId === null, 403, 'A tenant context is required to create supervisors.');

        $data = $request->validated();
        $employeeCode = EmployeeCodeGenerator::nextSupervisorCode($tenantId, $data['employee_code'] ?? null);

        $supervisor = Supervisor::create([
            'tenant_id' => $tenantId,
            'user_id' => $data['user_id'],
            'employee_code' => $employeeCode,
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'] ?? null,
            'phone' => $data['phone'] ?? null,
            'is_active' => true,
        ]);

        AuditLogger::log('supervisor.created', $supervisor, [], [
            'employee_code' => $supervisor->employee_code,
            'name' => trim($supervisor->first_name.' '.$supervisor->last_name),
            'phone' => $supervisor->phone,
            'user_id' => $supervisor->user_id,
        ]);

        return redirect()->route('supervisors.show', $supervisor)->with('status', 'Supervisor created.');
    }

    public function show(Supervisor $supervisor): View
    {
        $this->authorize('view', $supervisor);

        $supervisor->load('user');

        return view('pages.supervisors.show', compact('supervisor'));
    }

    public function update(UpdateSupervisorRequest $request, Supervisor $supervisor): RedirectResponse
    {
        abort_if(TenantContext::currentId() === null || $supervisor->tenant_id !== TenantContext::currentId(), 403);

        $data = $request->validated();
        $oldValues = [
            'employee_code' => $supervisor->employee_code,
            'first_name' => $supervisor->first_name,
            'last_name' => $supervisor->last_name,
            'phone' => $supervisor->phone,
        ];

        $supervisor->update([
            'employee_code' => $data['employee_code'] ?? $supervisor->employee_code,
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'] ?? null,
            'phone' => $data['phone'] ?? null,
        ]);

        $newValues = [
            'employee_code' => $supervisor->employee_code,
            'first_name' => $supervisor->first_name,
            'last_name' => $supervisor->last_name,
            'phone' => $supervisor->phone,
        ];

        AuditLogger::log('supervisor.updated', $supervisor, $oldValues, $newValues);

        return redirect()->route('supervisors.show', $supervisor)->with('status', 'Supervisor updated.');
    }

    public function deactivate(Supervisor $supervisor): RedirectResponse
    {
        $this->authorize('deactivate', $supervisor);

        abort_if(TenantContext::currentId() === null || $supervisor->tenant_id !== TenantContext::currentId(), 403);

        $supervisor->update(['is_active' => ! $supervisor->is_active]);

        AuditLogger::log($supervisor->is_active ? 'supervisor.activated' : 'supervisor.deactivated', $supervisor, [], [
            'is_active' => $supervisor->is_active,
            'employee_code' => $supervisor->employee_code,
        ]);

        return back()->with('status', $supervisor->is_active ? 'Supervisor activated.' : 'Supervisor deactivated.');
    }

    /**
     * Users in the current tenant that are not already linked to a supervisor or salesman profile.
     */
    private function assignableUsers(): array
    {
        $tenantId = TenantContext::currentId();

        if ($tenantId === null) {
            return [];
        }

        return User::query()
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->whereDoesntHave('supervisorProfile')
            ->whereDoesntHave('salesmanProfile')
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (User $user) => [$user->id => $user->name.' ('.$user->email.')'])
            ->all();
    }
}
