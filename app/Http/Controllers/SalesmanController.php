<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSalesmanRequest;
use App\Http\Requests\UpdateSalesmanRequest;
use App\Models\Salesman;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\FieldProfiles\EmployeeCodeGenerator;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SalesmanController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Salesman::class);

        $query = Salesman::query()->with(['tenant', 'user', 'devices']);

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

        $salesmen = $query->orderBy('first_name')->paginate(20)->withQueryString();

        return view('pages.salesmen.index', compact('salesmen'));
    }

    public function create(): View
    {
        $this->authorize('create', Salesman::class);

        $users = $this->assignableUsers();

        return view('pages.salesmen.create', compact('users'));
    }

    public function store(StoreSalesmanRequest $request): RedirectResponse
    {
        $tenantId = TenantContext::currentId();

        abort_if($tenantId === null, 403, 'A tenant context is required to create salesmen.');

        $data = $request->validated();
        $employeeCode = EmployeeCodeGenerator::nextSalesmanCode($tenantId, $data['employee_code'] ?? null);

        $salesman = Salesman::create([
            'tenant_id' => $tenantId,
            'user_id' => $data['user_id'] ?? null,
            'employee_code' => $employeeCode,
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'] ?? null,
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'hire_date' => $data['hire_date'] ?? null,
            'designation' => $data['designation'] ?? null,
            'is_active' => true,
        ]);

        AuditLogger::log('salesman.created', $salesman, [], [
            'employee_code' => $salesman->employee_code,
            'name' => trim($salesman->first_name.' '.$salesman->last_name),
            'phone' => $salesman->phone,
            'user_id' => $salesman->user_id,
        ]);

        return redirect()->route('salesmen.show', $salesman)->with('status', 'Salesman created.');
    }

    public function show(Salesman $salesman): View
    {
        $this->authorize('view', $salesman);

        $salesman->load(['user', 'devices']);

        return view('pages.salesmen.show', compact('salesman'));
    }

    public function update(UpdateSalesmanRequest $request, Salesman $salesman): RedirectResponse
    {
        abort_if(TenantContext::currentId() === null || $salesman->tenant_id !== TenantContext::currentId(), 403);

        $data = $request->validated();
        $oldValues = [
            'employee_code' => $salesman->employee_code,
            'first_name' => $salesman->first_name,
            'last_name' => $salesman->last_name,
            'phone' => $salesman->phone,
            'email' => $salesman->email,
            'hire_date' => $salesman->hire_date?->toDateString(),
            'designation' => $salesman->designation,
        ];

        $salesman->update([
            'employee_code' => $data['employee_code'] ?? $salesman->employee_code,
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'] ?? null,
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'hire_date' => $data['hire_date'] ?? null,
            'designation' => $data['designation'] ?? null,
        ]);

        $newValues = [
            'employee_code' => $salesman->employee_code,
            'first_name' => $salesman->first_name,
            'last_name' => $salesman->last_name,
            'phone' => $salesman->phone,
            'email' => $salesman->email,
            'hire_date' => $salesman->hire_date?->toDateString(),
            'designation' => $salesman->designation,
        ];

        AuditLogger::log('salesman.updated', $salesman, $oldValues, $newValues);

        return redirect()->route('salesmen.show', $salesman)->with('status', 'Salesman updated.');
    }

    public function deactivate(Salesman $salesman): RedirectResponse
    {
        $this->authorize('deactivate', $salesman);

        abort_if(TenantContext::currentId() === null || $salesman->tenant_id !== TenantContext::currentId(), 403);

        $salesman->update(['is_active' => ! $salesman->is_active]);

        AuditLogger::log($salesman->is_active ? 'salesman.activated' : 'salesman.deactivated', $salesman, [], [
            'is_active' => $salesman->is_active,
            'employee_code' => $salesman->employee_code,
        ]);

        return back()->with('status', $salesman->is_active ? 'Salesman activated.' : 'Salesman deactivated.');
    }

    /**
     * Users in the current tenant that are not already linked to a salesman profile.
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
            ->whereDoesntHave('salesmanProfile')
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (User $user) => [$user->id => $user->name.' ('.$user->email.')'])
            ->all();
    }
}
