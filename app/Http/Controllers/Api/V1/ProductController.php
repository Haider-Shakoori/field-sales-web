<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Support\Http\ApiResponse;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Product::query();

        if (TenantContext::hasContext()) {
            $query->where('tenant_id', TenantContext::currentId());
        }

        if ($search = $request->string('filter.search')->toString()) {
            $query->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('sku', 'like', "%{$search}%"));
        }

        if ($category = $request->string('filter.category')->toString()) {
            $query->where('category', $category);
        }

        if ($status = $request->string('filter.is_active')->toString()) {
            $query->where('is_active', $status === 'true');
        }

        $user = $request->user();
        if ($user->hasRole('salesman') || $user->hasRole('supervisor')) {
            $query->where('is_active', true);
        }

        $sort = $request->string('sort', '-created_at')->toString();
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $sortField = ltrim($sort, '-');
        $allowedSortFields = ['created_at', 'updated_at', 'name', 'sku', 'price'];
        if (in_array($sortField, $allowedSortFields)) {
            $query->orderBy($sortField, $direction);
        }

        $perPage = min($request->integer('per_page', 25), 100);
        $products = $query->paginate($perPage);

        return ApiResponse::success(
            ProductResource::collection($products),
            [
                'page' => $products->currentPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
                'last_page' => $products->lastPage(),
            ]
        );
    }

    public function show(Product $product): JsonResponse
    {
        abort_if(TenantContext::currentId() !== null && $product->tenant_id !== TenantContext::currentId(), 404);

        $product->load('priceListItems.priceList');

        return ApiResponse::success(new ProductResource($product));
    }
}
