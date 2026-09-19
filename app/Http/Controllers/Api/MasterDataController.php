<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\MobileStoreCustomerRequest;
use App\Models\Customer;
use App\Models\PriceList;
use App\Models\PriceListItem;
use App\Models\Product;
use App\Models\RouteCustomer;
use App\Models\SalesRoute;
use App\Models\SalesmanAssignment;
use App\Models\Territory;
use App\Support\ApiResponse;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class MasterDataController extends Controller
{
    public function customers(Request $request): JsonResponse
    {
        $this->ensurePermission($request, 'customers:view');

        $query = Customer::with([
            'branch',
            'territory',
            'priceList',
            'routeMemberships.route',
        ])->orderBy('name')->orderBy('id');

        $this->applyUpdatedSince($request, $query);
        $this->applySalesmanCustomerScope($request, $query);

        return $this->paginated(
            $query->paginate($this->perPage($request)),
            fn (Customer $customer) => $this->customerPayload($customer)
        );
    }

    public function storeCustomer(MobileStoreCustomerRequest $request): JsonResponse
    {
        $user = $request->user()->load(['salesman', 'tenant']);
        $validated = $request->validated();

        $existing = Customer::where('offline_uuid', $validated['offline_uuid'])->first();

        if ($existing) {
            return ApiResponse::success(
                $this->customerPayload(
                    $existing->load([
                        'branch',
                        'territory',
                        'priceList',
                        'routeMemberships.route',
                    ])
                )
            );
        }

        $assignment = $this->currentSalesmanAssignment($request);
        $code = strtoupper(
            ($validated['code'] ?? null)
                ?: 'CUS-'.substr(str_replace('-', '', $validated['offline_uuid']), 0, 12)
        );

        if (Customer::where('code', $code)->exists()) {
            throw ValidationException::withMessages([
                'code' => 'The customer code is already in use.',
            ]);
        }

        $priceListId = null;

        if (! empty($validated['price_list_id'])) {
            $priceListId = PriceList::where('uuid', $validated['price_list_id'])->value('id');

            if (! $priceListId) {
                throw ValidationException::withMessages([
                    'price_list_id' => 'The selected price list is invalid.',
                ]);
            }
        }

        $customer = Customer::create([
            // The client's stable offline UUID becomes the canonical public UUID.
            'uuid' => $validated['offline_uuid'],
            'offline_uuid' => $validated['offline_uuid'],
            'branch_id' => $assignment?->branch_id ?? $user->branch_id,
            'territory_id' => $assignment?->territory_id,
            'price_list_id' => $priceListId,
            'code' => $code,
            'name' => $validated['name'],
            'contact_person' => $validated['contact_person'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'alternate_phone' => $validated['alternate_phone'] ?? null,
            'email' => $validated['email'] ?? null,
            'address' => $validated['address'] ?? null,
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'geofence_radius_meters' => $validated['geofence_radius_meters'] ?? 100,
            'created_by' => $user->id,
            'is_active' => true,
        ]);

        return ApiResponse::success(
            $this->customerPayload(
                $customer->load([
                    'branch',
                    'territory',
                    'priceList',
                    'routeMemberships.route',
                ])
            ),
            201
        );
    }

    public function territories(Request $request): JsonResponse
    {
        $this->ensurePermission($request, 'customers:view');

        $query = Territory::with('branch')
            ->orderBy('name')
            ->orderBy('id');

        $this->applyUpdatedSince($request, $query);

        if ($this->isSalesman($request)) {
            $assignment = $this->currentSalesmanAssignment($request);

            if ($assignment?->territory_id) {
                $query->whereKey($assignment->territory_id);
            } elseif ($assignment?->branch_id) {
                $query->where('branch_id', $assignment->branch_id);
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        return $this->paginated(
            $query->paginate($this->perPage($request)),
            fn (Territory $territory) => [
                'id' => $territory->uuid,
                'code' => $territory->code,
                'name' => $territory->name,
                'description' => $territory->description,
                'polygon' => $territory->polygon,
                'branch_id' => $territory->branch?->uuid,
                'is_active' => $territory->is_active,
                'updated_at' => $territory->updated_at?->toISOString(),
            ]
        );
    }

    public function routes(Request $request): JsonResponse
    {
        $this->ensurePermission($request, 'customers:view');

        $query = SalesRoute::with(['branch', 'territory'])
            ->orderBy('name')
            ->orderBy('id');

        $this->applyUpdatedSince($request, $query);

        if ($this->isSalesman($request)) {
            $assignment = $this->currentSalesmanAssignment($request);

            if ($assignment?->route_id) {
                $query->whereKey($assignment->route_id);
            } elseif ($assignment?->territory_id) {
                $query->where('territory_id', $assignment->territory_id);
            } elseif ($assignment?->branch_id) {
                $query->where('branch_id', $assignment->branch_id);
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        return $this->paginated(
            $query->paginate($this->perPage($request)),
            fn (SalesRoute $route) => $this->routePayload($route)
        );
    }

    public function routeCustomers(Request $request, SalesRoute $route): JsonResponse
    {
        $this->ensurePermission($request, 'customers:view');
        $this->ensureRouteVisibleToSalesman($request, $route);

        $query = RouteCustomer::where('route_id', $route->id)
            ->with('customer')
            ->orderBy('sequence_number')
            ->orderBy('id');

        $this->applyUpdatedSince($request, $query);

        return $this->paginated(
            $query->paginate($this->perPage($request)),
            fn (RouteCustomer $membership) => [
                'id' => $membership->uuid,
                'route_id' => $route->uuid,
                'customer_id' => $membership->customer?->uuid,
                'sequence_number' => $membership->sequence_number,
                'planned_visit_minutes' => $membership->planned_visit_minutes,
                'notes' => $membership->notes,
                'updated_at' => $membership->updated_at?->toISOString(),
            ]
        );
    }

    public function products(Request $request): JsonResponse
    {
        $this->ensurePermission($request, 'catalog:view');

        $query = Product::query()
            ->orderBy('name')
            ->orderBy('id');

        $this->applyUpdatedSince($request, $query);

        return $this->paginated(
            $query->paginate($this->perPage($request)),
            fn (Product $product) => [
                'id' => $product->uuid,
                'sku' => $product->sku,
                'barcode' => $product->barcode,
                'name' => $product->name,
                'description' => $product->description,
                'unit' => $product->unit,
                'base_price' => (float) $product->base_price,
                'currency' => $product->currency,
                'is_active' => $product->is_active,
                'updated_at' => $product->updated_at?->toISOString(),
            ]
        );
    }

    public function priceLists(Request $request): JsonResponse
    {
        $this->ensurePermission($request, 'catalog:view');

        $query = PriceList::query()
            ->orderBy('name')
            ->orderBy('id');

        $this->applyUpdatedSince($request, $query);

        return $this->paginated(
            $query->paginate($this->perPage($request)),
            fn (PriceList $priceList) => $this->priceListPayload($priceList)
        );
    }

    public function priceListItems(Request $request, PriceList $priceList): JsonResponse
    {
        $this->ensurePermission($request, 'catalog:view');

        $query = PriceListItem::where('price_list_id', $priceList->id)
            ->with('product')
            ->orderBy('product_id')
            ->orderBy('min_quantity')
            ->orderBy('id');

        $this->applyUpdatedSince($request, $query);

        return $this->paginated(
            $query->paginate($this->perPage($request)),
            fn (PriceListItem $item) => [
                'id' => $item->uuid,
                'price_list_id' => $priceList->uuid,
                'product_id' => $item->product?->uuid,
                'min_quantity' => (float) $item->min_quantity,
                'price' => (float) $item->price,
                'updated_at' => $item->updated_at?->toISOString(),
            ]
        );
    }

    private function customerPayload(Customer $customer): array
    {
        return [
            'id' => $customer->uuid,
            'offline_uuid' => $customer->offline_uuid,
            'code' => $customer->code,
            'name' => $customer->name,
            'contact_person' => $customer->contact_person,
            'phone' => $customer->phone,
            'alternate_phone' => $customer->alternate_phone,
            'email' => $customer->email,
            'address' => $customer->address,
            'latitude' => $customer->latitude !== null ? (float) $customer->latitude : null,
            'longitude' => $customer->longitude !== null ? (float) $customer->longitude : null,
            'geofence_radius_meters' => $customer->geofence_radius_meters,
            'branch_id' => $customer->branch?->uuid,
            'territory_id' => $customer->territory?->uuid,
            'price_list_id' => $customer->priceList?->uuid,
            'route_ids' => $customer->relationLoaded('routeMemberships')
                ? $customer->routeMemberships
                    ->map(fn (RouteCustomer $membership) => $membership->route?->uuid)
                    ->filter()
                    ->values()
                    ->all()
                : [],
            'is_active' => $customer->is_active,
            'updated_at' => $customer->updated_at?->toISOString(),
        ];
    }

    private function routePayload(SalesRoute $route): array
    {
        return [
            'id' => $route->uuid,
            'code' => $route->code,
            'name' => $route->name,
            'weekdays' => $route->weekdays ?? [],
            'description' => $route->description,
            'branch_id' => $route->branch?->uuid,
            'territory_id' => $route->territory?->uuid,
            'is_active' => $route->is_active,
            'updated_at' => $route->updated_at?->toISOString(),
        ];
    }

    private function priceListPayload(PriceList $priceList): array
    {
        return [
            'id' => $priceList->uuid,
            'code' => $priceList->code,
            'name' => $priceList->name,
            'currency' => $priceList->currency,
            'effective_from' => $priceList->effective_from?->toDateString(),
            'effective_to' => $priceList->effective_to?->toDateString(),
            'is_active' => $priceList->is_active,
            'updated_at' => $priceList->updated_at?->toISOString(),
        ];
    }

    private function paginated(LengthAwarePaginator $page, callable $map): JsonResponse
    {
        return ApiResponse::success(
            collect($page->items())->map($map)->values()->all(),
            200,
            [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'has_more' => $page->hasMorePages(),
                'next_page' => $page->hasMorePages() ? $page->currentPage() + 1 : null,
            ]
        );
    }

    private function perPage(Request $request): int
    {
        return max(1, min(100, (int) $request->integer('per_page', 50)));
    }

    private function applyUpdatedSince(Request $request, Builder $query): void
    {
        if (! $request->filled('updated_since')) {
            return;
        }

        $validated = $request->validate([
            'updated_since' => ['date'],
        ]);

        $query->where(
            $query->getModel()->qualifyColumn('updated_at'),
            '>',
            CarbonImmutable::parse($validated['updated_since'])->utc()
        );
    }

    private function currentSalesmanAssignment(Request $request): ?SalesmanAssignment
    {
        $user = $request->user()->loadMissing(['salesman', 'tenant']);

        if (! $user->salesman) {
            return null;
        }

        $date = now($user->tenant->timezone)->toDateString();

        return SalesmanAssignment::where('salesman_id', $user->salesman->id)
            ->current($date)
            ->latest('effective_from')
            ->first();
    }

    private function applySalesmanCustomerScope(Request $request, Builder $query): void
    {
        if (! $this->isSalesman($request)) {
            return;
        }

        $assignment = $this->currentSalesmanAssignment($request);
        $userId = $request->user()->id;

        $query->where(function (Builder $scope) use ($assignment, $userId): void {
            $scope->where('created_by', $userId);

            if ($assignment?->route_id) {
                $scope->orWhereHas(
                    'routeMemberships',
                    fn (Builder $membership) => $membership
                        ->where('route_id', $assignment->route_id)
                );

                return;
            }

            if ($assignment?->territory_id) {
                $scope->orWhere('territory_id', $assignment->territory_id);

                return;
            }

            if ($assignment?->branch_id) {
                $scope->orWhere('branch_id', $assignment->branch_id);
            }
        });
    }

    private function ensureRouteVisibleToSalesman(Request $request, SalesRoute $route): void
    {
        if (! $this->isSalesman($request)) {
            return;
        }

        $assignment = $this->currentSalesmanAssignment($request);

        $allowed = $assignment !== null && (
            ($assignment->route_id && (int) $assignment->route_id === (int) $route->id)
            || (! $assignment->route_id
                && $assignment->territory_id
                && (int) $assignment->territory_id === (int) $route->territory_id)
            || (! $assignment->route_id
                && ! $assignment->territory_id
                && $assignment->branch_id
                && (int) $assignment->branch_id === (int) $route->branch_id)
        );

        abort_unless($allowed, 404);
    }

    private function isSalesman(Request $request): bool
    {
        return $request->user()->hasAnyRole(['salesman']);
    }

    private function ensurePermission(Request $request, string $permission): void
    {
        abort_unless($request->user()->hasPermission($permission), 403);
    }
}
