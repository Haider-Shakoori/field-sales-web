<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\PriceList;
use App\Models\Product;
use App\Models\SalesRoute;
use App\Models\Territory;
use App\Services\FieldScopeService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class MasterDataController extends Controller
{
    public function customers(Request $request, FieldScopeService $scope)
    {
        $user = $request->user()->load('salesman');

        $query = Customer::with(['branch:id,uuid,code,name', 'territory:id,uuid,code,name', 'route:id,uuid,code,name'])
            ->where('tenant_id', $user->tenant_id)
            ->where('is_active', true)
            ->orderBy('name');

        if (! $user->hasAnyRole(['super_admin', 'owner', 'admin', 'company_admin', 'sales_manager'])) {
            $query->whereIn('assigned_salesman_id', $scope->salesmanIds($user));
        }

        if ($request->filled('updated_since')) {
            $query->where('updated_at', '>', $request->date('updated_since'));
        }

        return ApiResponse::success($query->get()->map(fn (Customer $customer) => $this->customerPayload($customer))->values());
    }

    public function territories(Request $request)
    {
        $rows = Territory::with('branch:id,uuid,code,name')
            ->where('tenant_id', $request->user()->tenant_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return ApiResponse::success($rows);
    }

    public function routes(Request $request, FieldScopeService $scope)
    {
        $user = $request->user()->load('salesman');

        $query = SalesRoute::with(['territory:id,uuid,code,name'])
            ->where('tenant_id', $user->tenant_id)
            ->where('is_active', true)
            ->orderBy('name');

        if ($user->role === 'salesman' && $user->salesman) {
            $query->whereHas('customers', fn ($q) => $q->where('assigned_salesman_id', $user->salesman->id));
        } elseif (! $user->hasAnyRole(['super_admin', 'owner', 'admin', 'company_admin', 'sales_manager'])) {
            $ids = $scope->salesmanIds($user);
            $query->whereHas('customers', fn ($q) => $q->whereIn('assigned_salesman_id', $ids));
        }

        return ApiResponse::success($query->get());
    }

    public function routeCustomers(Request $request, SalesRoute $route, FieldScopeService $scope)
    {
        abort_unless((int) $route->tenant_id === (int) $request->user()->tenant_id, 404);

        $user = $request->user()->load('salesman');
        $customers = $route->customers()
            ->where('customers.tenant_id', $user->tenant_id)
            ->where('customers.is_active', true)
            ->orderBy('route_customers.sequence')
            ->get();

        if (! $user->hasAnyRole(['super_admin', 'owner', 'admin', 'company_admin', 'sales_manager'])) {
            $allowed = $scope->salesmanIds($user);
            $customers = $customers->whereIn('assigned_salesman_id', $allowed)->values();
        }

        return ApiResponse::success($customers->map(fn (Customer $customer) => $this->customerPayload($customer))->values());
    }

    public function products(Request $request)
    {
        $query = Product::where('tenant_id', $request->user()->tenant_id)
            ->where('is_active', true)
            ->orderBy('name');

        if ($request->filled('updated_since')) {
            $query->where('updated_at', '>', $request->date('updated_since'));
        }

        return ApiResponse::success($query->get());
    }

    public function priceLists(Request $request)
    {
        $rows = PriceList::with(['items.product:id,uuid,sku,name,unit'])
            ->where('tenant_id', $request->user()->tenant_id)
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('effective_from')->orWhereDate('effective_from', '<=', today());
            })
            ->where(function ($query) {
                $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', today());
            })
            ->orderBy('name')
            ->get();

        return ApiResponse::success($rows);
    }

    private function customerPayload(Customer $customer): array
    {
        return [
            'id' => $customer->id,
            'uuid' => $customer->uuid,
            'code' => $customer->code,
            'name' => $customer->name,
            'shop_name' => $customer->shop_name,
            'owner_name' => $customer->owner_name,
            'phone' => $customer->phone,
            'secondary_phone' => $customer->secondary_phone,
            'address' => $customer->address,
            'latitude' => $customer->latitude !== null ? (float) $customer->latitude : null,
            'longitude' => $customer->longitude !== null ? (float) $customer->longitude : null,
            'geofence_radius' => $customer->geofence_radius,
            'tier' => $customer->tier,
            'credit_limit' => (float) $customer->credit_limit,
            'payment_terms_days' => $customer->payment_terms_days,
            'visit_frequency' => $customer->visit_frequency,
            'price_list_id' => $customer->price_list_id,
            'route_id' => $customer->route_id,
            'territory_id' => $customer->territory_id,
            'branch_id' => $customer->branch_id,
            'updated_at' => $customer->updated_at?->toISOString(),
        ];
    }
}
