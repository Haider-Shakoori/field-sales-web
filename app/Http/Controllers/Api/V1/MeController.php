<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\TenantResource;
use App\Http\Resources\UserResource;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;

class MeController extends Controller
{
    public function show(): JsonResponse
    {
        $user = request()->user();
        $user->load('tenant');

        return ApiResponse::success([
            'user' => new UserResource($user),
            'tenant' => $user->tenant_id !== null
                ? new TenantResource($user->tenant)
                : null,
        ], meta: [
            'tenant_id' => $user->tenant_id,
            'role' => $user->role,
            'permissions' => $user->roles()->with('permissions')->get()
                ->pluck('permissions')
                ->flatten()
                ->pluck('name')
                ->unique()
                ->values()
                ->all(),
        ]);
    }
}
