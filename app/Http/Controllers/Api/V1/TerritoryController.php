<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\TerritoryResource;
use App\Models\Territory;
use App\Support\Http\ApiResponse;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TerritoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Territory::query()->with('branch');

        if (TenantContext::hasContext()) {
            $query->where('tenant_id', TenantContext::currentId());
        }

        if ($branchId = $request->string('filter.branch_id')->toString()) {
            $query->where('branch_id', $branchId);
        }

        if ($status = $request->string('filter.is_active')->toString()) {
            $query->where('is_active', $status === 'true');
        }

        if ($search = $request->string('filter.search')->toString()) {
            $query->where(fn ($q) => $q
                ->where('code', 'like', "%{$search}%")
                ->orWhere('name', 'like', "%{$search}%"));
        }

        $sort = $request->string('sort')->toString() ?? '-created_at';
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $sortField = ltrim($sort, '-');
        $allowedSortFields = ['created_at', 'updated_at', 'name', 'code'];
        if (in_array($sortField, $allowedSortFields)) {
            $query->orderBy($sortField, $direction);
        }

        $perPage = min($request->integer('per_page', 25), 100);
        $territories = $query->paginate($perPage);

        return ApiResponse::success(
            TerritoryResource::collection($territories),
            [
                'page' => $territories->currentPage(),
                'per_page' => $territories->perPage(),
                'total' => $territories->total(),
                'last_page' => $territories->lastPage(),
            ]
        );
    }

    public function store(Request $request): JsonResponse
    {
        $tenantId = TenantContext::currentId();
        abort_if($tenantId === null, 403, 'A tenant context is required to create territories.');
        abort_unless($request->user()->can('create', Territory::class), 403, 'Insufficient permissions to create territories.');

        $validated = $request->validate([
            'branch_id' => ['required', Rule::exists('branches', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId))],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', Rule::unique('territories', 'code')->where('tenant_id', $tenantId)],
            'description' => ['nullable', 'string', 'max:500'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'radius_km' => ['nullable', 'numeric', 'min:0', 'max:999.99'],
            'is_active' => ['boolean'],
        ]);

        $territory = Territory::create([
            'tenant_id' => $tenantId,
            'branch_id' => $validated['branch_id'],
            'name' => $validated['name'],
            'code' => $validated['code'],
            'description' => $validated['description'] ?? null,
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'radius_km' => $validated['radius_km'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return ApiResponse::success(new TerritoryResource($territory), status: 201);
    }

    public function show(Territory $territory): JsonResponse
    {
        abort_if(TenantContext::currentId() !== null && $territory->tenant_id !== TenantContext::currentId(), 404);

        $territory->load('branch');

        return ApiResponse::success(new TerritoryResource($territory));
    }

    public function update(Request $request, Territory $territory): JsonResponse
    {
        abort_if(TenantContext::currentId() !== null && $territory->tenant_id !== TenantContext::currentId(), 404);
        abort_unless($request->user()->can('update', $territory), 403, 'Insufficient permissions to update this territory.');

        $tenantId = TenantContext::currentId();

        $validated = $request->validate([
            'branch_id' => ['sometimes', Rule::exists('branches', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId))],
            'name' => ['sometimes', 'string', 'max:255'],
            'code' => ['sometimes', 'string', 'max:50', Rule::unique('territories', 'code')->where('tenant_id', $tenantId)->ignore($territory->id)],
            'description' => ['nullable', 'string', 'max:500'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'radius_km' => ['nullable', 'numeric', 'min:0', 'max:999.99'],
            'is_active' => ['boolean'],
        ]);

        $territory->update($validated);

        $territory->load('branch');

        return ApiResponse::success(new TerritoryResource($territory));
    }
}
