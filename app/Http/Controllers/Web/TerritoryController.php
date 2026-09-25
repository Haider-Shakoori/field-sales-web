<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTerritoryRequest;
use App\Http\Requests\UpdateTerritoryRequest;
use App\Models\Branch;
use App\Models\Territory;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TerritoryController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', Territory::class);

        return view('admin.territories.index', [
            'territories' => Territory::with('branch')
                ->withCount(['customers', 'routes'])
                ->orderBy('name')
                ->paginate(30),
            'mapTerritories' => Territory::with('branch')
                ->whereNotNull('polygon')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Territory::class);

        return view('admin.territories.create', [
            'branches' => Branch::active()->orderBy('name')->get(),
        ]);
    }

    public function store(
        StoreTerritoryRequest $request,
        AuditLogger $audit,
    ): RedirectResponse {
        $validated = $request->validated();

        $territory = Territory::create([
            ...$validated,
            'code' => strtoupper($validated['code']),
            'polygon' => $this->decodePolygon($validated['polygon'] ?? null),
        ]);

        $audit->record('territory.created', $territory, [], $this->auditValues($territory));

        return redirect()
            ->route('admin.territories.show', $territory)
            ->with('status', 'Territory created.');
    }

    public function show(Territory $territory): View
    {
        Gate::authorize('view', $territory);

        return view('admin.territories.show', [
            'territory' => $territory->load([
                'branch',
                'customers' => fn ($query) => $query->orderBy('name'),
                'routes' => fn ($query) => $query->orderBy('name'),
            ]),
        ]);
    }

    public function edit(Territory $territory): View
    {
        Gate::authorize('update', $territory);

        return view('admin.territories.edit', [
            'territory' => $territory,
            'branches' => Branch::active()->orderBy('name')->get(),
        ]);
    }

    public function update(
        UpdateTerritoryRequest $request,
        Territory $territory,
        AuditLogger $audit,
    ): RedirectResponse {
        $before = $this->auditValues($territory);
        $validated = $request->validated();

        $territory->update([
            ...$validated,
            'code' => strtoupper($validated['code']),
            'polygon' => $this->decodePolygon($validated['polygon'] ?? null),
        ]);

        $audit->record('territory.updated', $territory, $before, $this->auditValues($territory));

        return redirect()
            ->route('admin.territories.show', $territory)
            ->with('status', 'Territory updated.');
    }

    public function destroy(Territory $territory, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('delete', $territory);

        if ($territory->customers()->exists()
            || $territory->routes()->exists()
            || $territory->salesmanAssignments()->exists()
            || $territory->supervisorAssignments()->exists()) {
            throw ValidationException::withMessages([
                'territory' => 'This territory is referenced by customers, routes, or assignment history. Deactivate it instead.',
            ]);
        }

        $before = $this->auditValues($territory);
        $audit->record('territory.deleted', $territory, $before);
        $territory->delete();

        return redirect()
            ->route('admin.territories.index')
            ->with('status', 'Territory deleted.');
    }

    private function decodePolygon(?string $polygon): ?array
    {
        if (! $polygon) {
            return null;
        }

        return json_decode($polygon, true, 512, JSON_THROW_ON_ERROR);
    }

    private function auditValues(Territory $territory): array
    {
        return [
            'branch_id' => $territory->branch_id,
            'code' => $territory->code,
            'name' => $territory->name,
            'description' => $territory->description,
            'polygon' => $territory->polygon,
            'is_active' => $territory->is_active,
        ];
    }
}
