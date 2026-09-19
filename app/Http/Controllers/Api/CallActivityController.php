<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerCallActivity;
use App\Support\ApiResponse;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CallActivityController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission('customers:view'), 403);

        $validated = $request->validate([
            'offline_uuid' => ['required', 'uuid'],
            'customer_id' => ['required', 'uuid'],
            'phone_number' => ['nullable', 'string', 'max:60'],
            'called_at' => ['required', 'date'],
            'outcome' => ['nullable', Rule::in(CustomerCallActivity::OUTCOMES)],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $user = $request->user()->load('salesman');
        abort_unless($user->salesman?->is_active, 403);

        $existing = CustomerCallActivity::where('uuid', $validated['offline_uuid'])->first();

        if ($existing) {
            abort_unless((int) $existing->user_id === (int) $user->id, 409);

            return ApiResponse::success($this->payload($existing->load('customer')));
        }

        $calledAt = CarbonImmutable::parse($validated['called_at'])->utc();

        if ($calledAt->gt(now()->addMinutes(5))) {
            return ApiResponse::error(
                'Call time is too far in the future.',
                422,
                null,
                'VALIDATION_ERROR',
            );
        }

        $customer = Customer::where('uuid', $validated['customer_id'])
            ->active()
            ->firstOrFail();

        $device = $request->attributes->get('device');

        $activity = CustomerCallActivity::create([
            'uuid' => $validated['offline_uuid'],
            'user_id' => $user->id,
            'salesman_id' => $user->salesman->id,
            'device_id' => $device->id,
            'customer_id' => $customer->id,
            'phone_number' => $validated['phone_number']
                ?? $customer->phone
                ?? $customer->alternate_phone,
            'called_at' => $calledAt,
            'outcome' => $validated['outcome'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        return ApiResponse::success(
            $this->payload($activity->load('customer')),
            201,
        );
    }

    public function history(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission('customers:view'), 403);

        $validated = $request->validate([
            'customer_id' => ['nullable', 'uuid'],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
        ]);

        $customerId = null;

        if (! empty($validated['customer_id'])) {
            $customerId = Customer::where('uuid', $validated['customer_id'])
                ->value('id');

            abort_if($customerId === null, 404);
        }

        $page = CustomerCallActivity::with('customer')
            ->where('user_id', $request->user()->id)
            ->when($customerId, fn ($query) => $query->where('customer_id', $customerId))
            ->orderByDesc('called_at')
            ->paginate((int) ($validated['per_page'] ?? 30));

        return ApiResponse::success(
            collect($page->items())
                ->map(fn (CustomerCallActivity $activity) => $this->payload($activity))
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

    private function payload(CustomerCallActivity $activity): array
    {
        return [
            'id' => $activity->uuid,
            'customer_id' => $activity->customer?->uuid,
            'customer_name' => $activity->customer?->name,
            'phone_number' => $activity->phone_number,
            'called_at' => $activity->called_at?->toISOString(),
            'outcome' => $activity->outcome,
            'notes' => $activity->notes,
        ];
    }
}
