<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTerritoryRequest;
use App\Http\Requests\UpdateTerritoryRequest;
use App\Models\Branch;
use App\Models\Territory;
use App\Support\Audit\AuditLogger;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TerritoryController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Territory::class);

        $query = Territory::query()->with(['branch']);

        if (TenantContext::hasContext()) {
            $query->where('tenant_id', TenantContext::currentId());
        }

        if ($search = $request->string('search')->trim()->toString()) {
            $query->where(fn ($q) => $q
                ->where('code', 'like', "%{$search}%")
                ->orWhere('name', 'like', "%{$search}%"));
        }

        if ($branchId = $request->string('branch_id')->toString()) {
            $query->where('branch_id', $branchId);
        }

        if ($status = $request->string('status')->toString()) {
            $query->where('is_active', $status === 'active');
        }

        $territories = $query->orderBy('name')->paginate(20)->withQueryString();

        $branches = Branch::query()
            ->where('tenant_id', TenantContext::currentId())
            ->active()
            ->orderBy('name')
            ->get();

        return view('pages.territories.index', compact('territories', 'branches'));
    }

    public function create(): View
    {
        $this->authorize('create', Territory::class);

        $tenantId = TenantContext::currentId();
        abort_if($tenantId === null, 403, 'A tenant context is required to create territories.');

        $branches = Branch::query()
            ->where('tenant_id', $tenantId)
            ->active()
            ->orderBy('name')
            ->get();

        return view('pages.territories.create', compact('branches'));
    }

    public function store(StoreTerritoryRequest $request): RedirectResponse
    {
        $tenantId = TenantContext::currentId();
        abort_if($tenantId === null, 403, 'A tenant context is required to create territories.');

        $data = $request->validated();

        $territory = Territory::create([
            'tenant_id' => $tenantId,
            'branch_id' => $data['branch_id'],
            'name' => $data['name'],
            'code' => $data['code'],
            'description' => $data['description'] ?? null,
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'radius_km' => $data['radius_km'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);

        return redirect()->route('territories.show', $territory)->with('status', 'Territory created.');
    }

    public function show(Territory $territory): View
    {
        $this->authorize('view', $territory);

        $territory->load(['branch', 'routes']);

        return view('pages.territories.show', compact('territory'));
    }

    public function edit(Territory $territory): View
    {
        $this->authorize('update', $territory);

        $tenantId = TenantContext::currentId();
        abort_if($tenantId === null || $territory->tenant_id !== $tenantId, 403);

        $branches = Branch::query()
            ->where('tenant_id', $tenantId)
            ->active()
            ->orderBy('name')
            ->get();

        return view('pages.territories.edit', compact('territory', 'branches'));
    }

    public function update(UpdateTerritoryRequest $request, Territory $territory): RedirectResponse
    {
        abort_if(TenantContext::currentId() === null || $territory->tenant_id !== TenantContext::currentId(), 403);

        $data = $request->validated();
        $oldValues = [
            'branch_id' => $territory->branch_id,
            'name' => $territory->name,
            'code' => $territory->code,
            'description' => $territory->description,
            'latitude' => $territory->latitude,
            'longitude' => $territory->longitude,
            'radius_km' => $territory->radius_km,
            'is_active' => $territory->is_active,
        ];

        $territory->update([
            'branch_id' => $data['branch_id'],
            'name' => $data['name'],
            'code' => $data['code'],
            'description' => $data['description'] ?? null,
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'radius_km' => $data['radius_km'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);

        $newValues = [
            'branch_id' => $territory->branch_id,
            'name' => $territory->name,
            'code' => $territory->code,
            'description' => $territory->description,
            'latitude' => $territory->latitude,
            'longitude' => $territory->longitude,
            'radius_km' => $territory->radius_km,
            'is_active' => $territory->is_active,
        ];

        AuditLogger::log('territory.updated', $territory, $oldValues, $newValues);

        return redirect()->route('territories.show', $territory)->with('status', 'Territory updated.');
    }

    public function deactivate(Territory $territory): RedirectResponse
    {
        $this->authorize('deactivate', $territory);

        abort_if(TenantContext::currentId() === null || $territory->tenant_id !== TenantContext::currentId(), 403);

        $territory->update(['is_active' => ! $territory->is_active]);

        AuditLogger::log($territory->is_active ? 'territory.activated' : 'territory.deactivated', $territory, [], [
            'is_active' => $territory->is_active,
            'code' => $territory->code,
            'name' => $territory->name,
        ]);

        return back()->with('status', $territory->is_active ? 'Territory activated.' : 'Territory deactivated.');
    }
}
