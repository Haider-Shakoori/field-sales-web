<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreSupervisorAssignmentRequest;
use App\Http\Requests\Api\V1\UpdateSupervisorAssignmentRequest;
use App\Http\Resources\SupervisorAssignmentResource;
use App\Models\SupervisorAssignment;
use App\Support\Http\ApiResponse;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;

class SupervisorAssignmentController extends Controller
{
    public function index(): JsonResponse
    {
        $query = SupervisorAssignment::query()->with(['supervisor', 'branch', 'territory']);

        if (TenantContext::hasContext()) {
            $query->where('tenant_id', TenantContext::currentId());
        }

        $assignments = $query->orderByDesc('effective_from')->paginate(20);

        return ApiResponse::success(
            SupervisorAssignmentResource::collection($assignments),
            [
                'page' => $assignments->currentPage(),
                'per_page' => $assignments->perPage(),
                'total' => $assignments->total(),
                'last_page' => $assignments->lastPage(),
            ]
        );
    }

    public function store(StoreSupervisorAssignmentRequest $request): JsonResponse
    {
        $tenantId = TenantContext::currentId();
        abort_if($tenantId === null, 403, 'A tenant context is required to create supervisor assignments.');

        $validated = $request->validated();

        $assignment = SupervisorAssignment::create([
            'tenant_id' => $tenantId,
            'supervisor_id' => $validated['supervisor_id'],
            'branch_id' => $validated['branch_id'],
            'territory_id' => $validated['territory_id'],
            'effective_from' => $validated['effective_from'],
            'effective_to' => $validated['effective_to'] ?? null,
        ]);

        $assignment->load(['supervisor', 'branch', 'territory']);

        return ApiResponse::success(new SupervisorAssignmentResource($assignment), status: 201);
    }

    public function show(SupervisorAssignment $assignment): JsonResponse
    {
        abort_if(TenantContext::currentId() !== null && $assignment->tenant_id !== TenantContext::currentId(), 404);

        $assignment->load(['supervisor', 'branch', 'territory']);

        return ApiResponse::success(new SupervisorAssignmentResource($assignment));
    }

    public function update(UpdateSupervisorAssignmentRequest $request, SupervisorAssignment $assignment): JsonResponse
    {
        abort_if(TenantContext::currentId() !== null && $assignment->tenant_id !== TenantContext::currentId(), 404);

        $assignment->update($request->validated());

        $assignment->load(['supervisor', 'branch', 'territory']);

        return ApiResponse::success(new SupervisorAssignmentResource($assignment));
    }

    public function destroy(SupervisorAssignment $assignment): JsonResponse
    {
        abort_if(TenantContext::currentId() !== null && $assignment->tenant_id !== TenantContext::currentId(), 404);

        $assignment->delete();

        return ApiResponse::success(null, status: 204);
    }
}
