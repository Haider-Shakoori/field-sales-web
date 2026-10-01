<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerFollowUp;
use App\Models\SalesmanAssignment;
use App\Services\AuditLogger;
use App\Support\ApiResponse;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomerFollowUpController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        [$user, $salesman] = $this->salesman($request);
        $validated = $request->validate([
            'status' => ['nullable', Rule::in(CustomerFollowUp::STATUSES)],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
        ]);
        $page = CustomerFollowUp::query()
            ->with('customer')
            ->where('assigned_salesman_id', $salesman->id)
            ->when(
                $validated['status'] ?? null,
                fn (Builder $query, string $status) => $query->where('status', $status),
            )
            ->orderBy('due_at')
            ->paginate((int) ($validated['per_page'] ?? 50));

        return ApiResponse::success(
            collect($page->items())->map(fn (CustomerFollowUp $followUp) => $this->payload($followUp))->values()->all(),
            200,
            [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        );
    }

    public function store(
        Request $request,
        Customer $customer,
        AuditLogger $audit,
    ): JsonResponse {
        [$user, $salesman] = $this->salesman($request);
        $this->ensureCustomerVisible($request, $customer);

        $validated = $request->validate([
            'offline_uuid' => ['required', 'uuid'],
            'type' => ['required', Rule::in(CustomerFollowUp::TYPES)],
            'priority' => ['required', Rule::in(CustomerFollowUp::PRIORITIES)],
            'due_at' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $existing = CustomerFollowUp::where('uuid', $validated['offline_uuid'])->first();

        if ($existing) {
            abort_unless(
                (int) $existing->assigned_salesman_id === (int) $salesman->id,
                409,
            );

            return ApiResponse::success($this->payload($existing->load('customer')));
        }

        $followUp = CustomerFollowUp::create([
            'uuid' => $validated['offline_uuid'],
            'customer_id' => $customer->id,
            'assigned_salesman_id' => $salesman->id,
            'type' => $validated['type'],            'priority' => $validated['priority'],
            'status' => 'pending',
            'due_at' => CarbonImmutable::parse($validated['due_at'])->utc(),
            'notes' => $validated['notes'] ?? null,
            'created_by' => $user->id,
        ]);

        $audit->record('customer_follow_up.created_mobile', $followUp, [], [
            'customer_id' => $customer->id,
            'assigned_salesman_id' => $salesman->id,
            'type' => $followUp->type,
            'priority' => $followUp->priority,
            'due_at' => $followUp->due_at?->toISOString(),
        ]);

        return ApiResponse::success($this->payload($followUp->load('customer')), 201);
    }

    public function updateStatus(
        Request $request,
        CustomerFollowUp $followUp,
        AuditLogger $audit,
    ): JsonResponse {
        [$user, $salesman] = $this->salesman($request);
        abort_unless(
            (int) $followUp->assigned_salesman_id === (int) $salesman->id,
            404,
        );

        $validated = $request->validate([
            'status' => ['required', Rule::in(['completed', 'cancelled'])],
            'completion_note' => ['nullable', 'string', 'max:5000'],
        ]);

        $before = [
            'status' => $followUp->status,
            'completed_at' => $followUp->completed_at?->toISOString(),
            'completion_note' => $followUp->completion_note,
        ];
        $completed = $validated['status'] === 'completed';

        $followUp->update([
            'status' => $validated['status'],
            'completed_at' => $completed ? now() : null,
            'completed_by' => $completed ? $user->id : null,
            'completion_note' => $validated['completion_note'] ?? null,
        ]);

        $audit->record(
            'customer_follow_up.status_changed_mobile',
            $followUp,
            $before,
            [
                'status' => $followUp->status,
                'completed_at' => $followUp->completed_at?->toISOString(),
                'completion_note' => $followUp->completion_note,
            ],
        );

        return ApiResponse::success($this->payload($followUp->fresh()->load('customer')));
    }

    private function salesman(Request $request): array
    {
        abort_unless($request->user()->hasPermission('customers:view'), 403);

        $user = $request->user()->loadMissing(['salesman', 'tenant']);
        abort_unless($user->salesman?->is_active, 403);

        return [$user, $user->salesman];
    }

    private function ensureCustomerVisible(Request $request, Customer $customer): void
    {
        $query = Customer::query()->whereKey($customer->id);
        $this->applySalesmanCustomerScope($request, $query);

        abort_unless($query->exists(), 404);
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
        if (! $request->user()->hasAnyRole(['salesman'])) {
            return;
        }

        $user = $request->user()->loadMissing('salesman');
        $assignment = $this->currentSalesmanAssignment($request);
        $userId = $user->id;
        $salesmanId = $user->salesman?->id;

        $query->where(function (Builder $scope) use ($assignment, $salesmanId, $userId): void {
            $scope->where('created_by', $userId);

            if ($assignment?->route_id) {
                $scope->orWhereHas('routeMemberships', function (Builder $membership) use ($assignment): void {
                    $membership->where('route_id', $assignment->route_id);
                });

                return;
            }

            if ($salesmanId) {
                $scope->orWhere('assigned_salesman_id', $salesmanId);
            }

            if ($assignment?->territory_id) {
                $scope->orWhere(function (Builder $legacy) use ($assignment): void {
                    $legacy->whereNull('assigned_salesman_id')
                        ->where('territory_id', $assignment->territory_id);
                });

                return;
            }

            if ($assignment?->branch_id) {
                $scope->orWhere(function (Builder $legacy) use ($assignment): void {
                    $legacy->whereNull('assigned_salesman_id')
                        ->where('branch_id', $assignment->branch_id);
                });
            }
        });
    }

    private function payload(CustomerFollowUp $followUp): array
    {
        return [
            'id' => $followUp->uuid,
            'customer_id' => $followUp->customer?->uuid,
            'customer_name' => $followUp->customer?->name,
            'type' => $followUp->type,
            'priority' => $followUp->priority,
            'status' => $followUp->status,
            'due_at' => $followUp->due_at?->toISOString(),
            'notes' => $followUp->notes,
            'completed_at' => $followUp->completed_at?->toISOString(),
            'completion_note' => $followUp->completion_note,
            'created_at' => $followUp->created_at?->toISOString(),
            'updated_at' => $followUp->updated_at?->toISOString(),
        ];
    }
}
