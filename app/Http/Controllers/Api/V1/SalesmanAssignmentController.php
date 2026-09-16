<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreSalesmanAssignmentRequest;
use App\Http\Requests\Api\V1\UpdateSalesmanAssignmentRequest;
use App\Http\Resources\SalesmanAssignmentResource;
use App\Models\SalesmanAssignment;
use App\Support\Http\ApiResponse;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;

class SalesmanAssignmentController extends Controller
{
    public function index(): JsonResponse
    {
        $query = SalesmanAssignment::query()->with(['salesman', 'branch', 'territory', 'route', 'supervisor', 'createdBy']);

        if (TenantContext::hasContext()) {
            $query->where('tenant_id', TenantContext::currentId());
        }

        $assignments = $query->orderByDesc('effective_from')->paginate(20);

        return ApiResponse::success(
            SalesmanAssignmentResource::collection($assignments),
            [
                'page' => $assignments->currentPage(),
                'per_page' => $assignments->perPage(),
                'total' => $assignments->total(),
                'last_page' => $assignments->lastPage(),
            ]
        );
    }

    public function store(StoreSalesmanAssignmentRequest $request): JsonResponse
    {
        $tenantId = TenantContext::currentId();
        abort_if($tenantId === null, 403, 'A tenant context is required to create salesman assignments.');

        $validated = $request->validated();

        // Close any currently active assignment for this salesman so the new one becomes current.
        SalesmanAssignment::query()
            ->where('tenant_id', $tenantId)
            ->where('salesman_id', $validated['salesman_id'])
            ->where(function ($q) {
                $q->whereNull('effective_to')
                    ->orWhere('effective_to', '>=', now()->startOfDay());
            })
            ->where('effective_from', '<=', now()->endOfDay())
            ->update(['effective_to' => now()->subDay()->toDateString()]);

        $assignment = SalesmanAssignment::create([
            'tenant_id' => $tenantId,
            'salesman_id' => $validated['salesman_id'],
            'branch_id' => $validated['branch_id'],
            'territory_id' => $validated['territory_id'],
            'route_id' => $validated['route_id'] ?? null,
            'supervisor_id' => $validated['supervisor_id'] ?? null,
            'effective_from' => $validated['effective_from'],
            'effective_to' => $validated['effective_to'] ?? null,
            'created_by' => $request->user()?->id,
        ]);

        $assignment->load(['salesman', 'branch', 'territory', 'route', 'supervisor', 'createdBy']);

        return ApiResponse::success(new SalesmanAssignmentResource($assignment), status: 201);
    }

    public function show(SalesmanAssignment $assignment): JsonResponse
    {
        abort_if(TenantContext::currentId() !== null && $assignment->tenant_id !== TenantContext::currentId(), 404);

        $assignment->load(['salesman', 'branch', 'territory', 'route', 'supervisor', 'createdBy']);

        return ApiResponse::success(new SalesmanAssignmentResource($assignment));
    }

    public function update(UpdateSalesmanAssignmentRequest $request, SalesmanAssignment $assignment): JsonResponse
    {
        abort_if(TenantContext::currentId() !== null && $assignment->tenant_id !== TenantContext::currentId(), 404);

        $assignment->update($request->validated());

        $assignment->load(['salesman', 'branch', 'territory', 'route', 'supervisor', 'createdBy']);

        return ApiResponse::success(new SalesmanAssignmentResource($assignment));
    }

    public function destroy(SalesmanAssignment $assignment): JsonResponse
    {
        abort_if(TenantContext::currentId() !== null && $assignment->tenant_id !== TenantContext::currentId(), 404);

        $assignment->delete();

        return ApiResponse::success(null, status: 204);
    }
}
