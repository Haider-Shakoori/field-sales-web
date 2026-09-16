<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PriceListResource;
use App\Models\PriceList;
use App\Support\Http\ApiResponse;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PriceListController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = PriceList::query()->withCount('items');

        if (TenantContext::hasContext()) {
            $query->where('tenant_id', TenantContext::currentId());
        }

        if ($search = $request->string('filter.search')->toString()) {
            $query->where('name', 'like', "%{$search}%");
        }

        if ($status = $request->string('filter.is_active')->toString()) {
            $query->where('is_active', $status === 'true');
        }

        $sort = $request->string('sort', '-created_at')->toString();
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $sortField = ltrim($sort, '-');
        $allowedSortFields = ['created_at', 'updated_at', 'name'];
        if (in_array($sortField, $allowedSortFields)) {
            $query->orderBy($sortField, $direction);
        }

        $perPage = min($request->integer('per_page', 25), 100);
        $priceLists = $query->paginate($perPage);

        return ApiResponse::success(
            PriceListResource::collection($priceLists),
            [
                'page' => $priceLists->currentPage(),
                'per_page' => $priceLists->perPage(),
                'total' => $priceLists->total(),
                'last_page' => $priceLists->lastPage(),
            ]
        );
    }
}
