<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Collection;
use App\Models\Customer;
use App\Models\CustomerVisit;
use App\Models\SalesmanAssignment;
use App\Services\CustomerBalanceService;
use App\Support\ApiResponse;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CollectionController extends Controller
{
    public function store(
        Request $request,
        CustomerBalanceService $balances,
    ): JsonResponse {
        abort_unless($request->user()->hasPermission('collections:view'), 403);

        $validated = $request->validate([
            'offline_uuid' => ['required', 'uuid'],
            'customer_id' => ['required', 'uuid'],
            'visit_id' => ['nullable', 'uuid'],
            'collected_at' => ['required', 'date'],
            'currency' => ['required', 'string', 'size:3'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'payment_method' => ['required', Rule::in(Collection::PAYMENT_METHODS)],
            'reference_number' => ['nullable', 'string', 'max:160'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['required', 'numeric', 'between:0,200'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        if (
            $validated['payment_method'] !== 'cash'
            && trim((string) ($validated['reference_number'] ?? '')) === ''
        ) {
            throw ValidationException::withMessages([
                'reference_number' => 'A reference number is required for non-cash collections.',
            ]);
        }

        if (
            (float) $validated['latitude'] === 0.0
            && (float) $validated['longitude'] === 0.0
        ) {
            throw ValidationException::withMessages([
                'latitude' => 'Invalid collection coordinates.',
            ]);
        }

        $user = $request->user()->load(['salesman', 'tenant']);
        abort_unless($user->salesman?->is_active, 403);

        $existing = Collection::where('uuid', $validated['offline_uuid'])->first();

        if ($existing) {
            abort_unless((int) $existing->user_id === (int) $user->id, 409);

            return ApiResponse::success(
                $this->payload($existing->load($this->relations()), $balances)
            );
        }

        $collectedAt = CarbonImmutable::parse($validated['collected_at'])->utc();

        if ($collectedAt->gt(now()->addMinutes(5))) {
            return ApiResponse::error(
                'Collection time is too far in the future.',
                422,
                null,
                'VALIDATION_ERROR',
            );
        }

        $customer = Customer::where('uuid', $validated['customer_id'])
            ->active()
            ->firstOrFail();

        $this->ensureCustomerVisible($request, $customer);

        $visit = null;

        if (! empty($validated['visit_id'])) {
            $visit = CustomerVisit::where('uuid', $validated['visit_id'])->first();

            if (! $visit) {
                return ApiResponse::error(
                    'The linked visit has not synchronized yet.',
                    409,
                    null,
                    'VISIT_NOT_SYNCED',
                );
            }

            if (
                (int) $visit->user_id !== (int) $user->id
                || (int) $visit->customer_id !== (int) $customer->id
            ) {
                return ApiResponse::error(
                    'The linked visit does not belong to this customer and salesman.',
                    422,
                    null,
                    'INVALID_VISIT_LINK',
                );
            }
        }

        $currency = strtoupper($validated['currency']);
        $amount = round((float) $validated['amount'], 4);
        $outstanding = $balances->outstanding($customer, $currency);
        $compactUuid = strtoupper(substr(
            str_replace('-', '', $validated['offline_uuid']),
            0,
            8
        ));

        $collection = Collection::create([
            'uuid' => $validated['offline_uuid'],
            'user_id' => $user->id,
            'salesman_id' => $user->salesman->id,
            'device_id' => $request->attributes->get('device')->id,
            'customer_id' => $customer->id,
            'visit_id' => $visit?->id,
            'receipt_number' => 'REC-'.$collectedAt->format('Ymd').'-'.$compactUuid,
            'collected_at' => $collectedAt,
            'currency' => $currency,
            'amount' => $amount,
            'payment_method' => $validated['payment_method'],
            'reference_number' => $validated['reference_number'] ?? null,
            'status' => 'pending',
            'latitude' => $validated['latitude'],
            'longitude' => $validated['longitude'],
            'accuracy' => $validated['accuracy'],
            'balance_before' => $outstanding,
            'overpayment_flag' => $amount > $outstanding + 0.0001,
            'notes' => $validated['notes'] ?? null,
        ]);

        return ApiResponse::success(
            $this->payload($collection->load($this->relations()), $balances),
            201,
        );
    }

    public function history(
        Request $request,
        CustomerBalanceService $balances,
    ): JsonResponse {
        abort_unless($request->user()->hasPermission('collections:view'), 403);

        $validated = $request->validate([
            'status' => ['nullable', Rule::in(Collection::STATUSES)],
            'customer_id' => ['nullable', 'uuid'],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
        ]);

        $query = Collection::with($this->relations())
            ->where('user_id', $request->user()->id)
            ->when(
                $validated['status'] ?? null,
                fn (Builder $query, string $status) => $query->where('status', $status)
            );

        if (! empty($validated['customer_id'])) {
            $customerId = Customer::where('uuid', $validated['customer_id'])->value('id');
            abort_if($customerId === null, 404);
            $query->where('customer_id', $customerId);
        }

        $page = $query
            ->orderByDesc('collected_at')
            ->paginate((int) ($validated['per_page'] ?? 30));

        return ApiResponse::success(
            collect($page->items())
                ->map(fn (Collection $collection) => $this->payload($collection, $balances))
                ->values()
                ->all(),
            200,
            [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        );
    }

    public function balances(
        Request $request,
        CustomerBalanceService $balances,
    ): JsonResponse {
        abort_unless($request->user()->hasPermission('collections:view'), 403);

        $query = Customer::active()->orderBy('name');
        $this->applySalesmanCustomerScope($request, $query);

        if ($request->filled('customer_id')) {
            $validated = $request->validate(['customer_id' => ['uuid']]);
            $query->where('uuid', $validated['customer_id']);
        }

        return ApiResponse::success(
            $balances->forCustomers($query->get())
        );
    }

    public function show(
        Request $request,
        Collection $collection,
        CustomerBalanceService $balances,
    ): JsonResponse {
        abort_unless($request->user()->hasPermission('collections:view'), 403);
        abort_unless((int) $collection->user_id === (int) $request->user()->id, 404);

        return ApiResponse::success(
            $this->payload($collection->load($this->relations()), $balances)
        );
    }

    private function relations(): array
    {
        return ['customer', 'visit'];
    }

    private function payload(
        Collection $collection,
        CustomerBalanceService $balances,
    ): array {
        return [
            'id' => $collection->uuid,
            'receipt_number' => $collection->receipt_number,
            'customer_id' => $collection->customer?->uuid,
            'customer_name' => $collection->customer?->name,
            'visit_id' => $collection->visit?->uuid,
            'collected_at' => $collection->collected_at?->toISOString(),
            'currency' => $collection->currency,
            'amount' => (float) $collection->amount,
            'payment_method' => $collection->payment_method,
            'reference_number' => $collection->reference_number,
            'status' => $collection->status,
            'latitude' => (float) $collection->latitude,
            'longitude' => (float) $collection->longitude,
            'accuracy' => (float) $collection->accuracy,
            'balance_before' => (float) $collection->balance_before,
            'overpayment_flag' => $collection->overpayment_flag,
            'notes' => $collection->notes,
            'status_note' => $collection->status_note,
            'status_changed_at' => $collection->status_changed_at?->toISOString(),
            'customer_balances' => $collection->customer
                ? $balances->forCustomer($collection->customer)
                : [],
        ];
    }

    private function ensureCustomerVisible(Request $request, Customer $customer): void
    {
        if (! $request->user()->hasAnyRole(['salesman'])) {
            return;
        }

        $query = Customer::whereKey($customer->id);
        $this->applySalesmanCustomerScope($request, $query);
        abort_unless($query->exists(), 404);
    }

    private function applySalesmanCustomerScope(Request $request, Builder $query): void
    {
        if (! $request->user()->hasAnyRole(['salesman'])) {
            return;
        }

        $user = $request->user()->loadMissing(['salesman', 'tenant']);
        $date = now($user->tenant->timezone)->toDateString();

        $assignment = $user->salesman
            ? SalesmanAssignment::where('salesman_id', $user->salesman->id)
                ->current($date)
                ->latest('effective_from')
                ->first()
            : null;

        $query->where(function (Builder $scope) use ($assignment, $user): void {
            $scope->where('created_by', $user->id);

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
}
