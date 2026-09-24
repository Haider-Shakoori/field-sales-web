<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Support\ApiResponse;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ExpenseController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission('expenses:view'), 403);

        $validated = $request->validate([
            'offline_uuid' => ['required', 'uuid'],
            'spent_at' => ['required', 'date'],
            'category' => ['required', Rule::in(Expense::CATEGORIES)],
            'currency' => ['required', 'string', 'size:3', 'regex:/^[A-Za-z]{3}$/'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:999999999999.9999'],
            'fuel_liters' => ['nullable', 'numeric', 'gt:0', 'max:999999999.999'],
            'fuel_unit_price' => ['nullable', 'numeric', 'gt:0', 'max:999999999999.9999'],
            'odometer_km' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'merchant' => ['nullable', 'string', 'max:160'],
            'reference_number' => ['nullable', 'string', 'max:160'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['required', 'numeric', 'between:0,10000'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $user = $request->user()->load('salesman');
        abort_unless($user->salesman?->is_active, 403);

        $existing = Expense::where('uuid', $validated['offline_uuid'])->first();

        if ($existing) {
            abort_unless((int) $existing->user_id === (int) $user->id, 409);

            return ApiResponse::success($this->payload($existing));
        }

        if (
            $validated['category'] !== 'fuel'
            && (
                isset($validated['fuel_liters'])
                || isset($validated['fuel_unit_price'])
                || isset($validated['odometer_km'])
            )
        ) {
            return ApiResponse::error(
                'Fuel details are only valid for fuel expenses.',
                422,
                null,
                'VALIDATION_ERROR',
            );
        }

        $spentAt = CarbonImmutable::parse($validated['spent_at'])->utc();

        if ($spentAt->gt(now()->addMinutes(5))) {
            return ApiResponse::error(
                'Expense time is too far in the future.',
                422,
                null,
                'VALIDATION_ERROR',
            );
        }

        $compactUuid = strtoupper(substr(
            str_replace('-', '', $validated['offline_uuid']),
            0,
            8
        ));

        $device = $request->attributes->get('device');

        $expense = Expense::create([
            'uuid' => $validated['offline_uuid'],
            'user_id' => $user->id,
            'salesman_id' => $user->salesman->id,
            'device_id' => $device->id,
            'expense_number' => 'EXP-'.$spentAt->format('Ymd').'-'.$compactUuid,
            'spent_at' => $spentAt,
            'category' => $validated['category'],
            'currency' => strtoupper($validated['currency']),
            'amount' => round((float) $validated['amount'], 4),
            'fuel_liters' => $validated['category'] === 'fuel'
                ? ($validated['fuel_liters'] ?? null)
                : null,
            'fuel_unit_price' => $validated['category'] === 'fuel'
                ? ($validated['fuel_unit_price']
                    ?? (isset($validated['fuel_liters'])
                        ? round(
                            (float) $validated['amount']
                            / (float) $validated['fuel_liters'],
                            4,
                        )
                        : null))
                : null,
            'odometer_km' => $validated['category'] === 'fuel'
                ? ($validated['odometer_km'] ?? null)
                : null,
            'merchant' => $validated['merchant'] ?? null,
            'reference_number' => $validated['reference_number'] ?? null,
            'latitude' => $validated['latitude'],
            'longitude' => $validated['longitude'],
            'accuracy' => $validated['accuracy'],
            'status' => 'pending',
            'notes' => $validated['notes'] ?? null,
        ]);

        return ApiResponse::success($this->payload($expense), 201);
    }

    public function history(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission('expenses:view'), 403);

        $validated = $request->validate([
            'status' => ['nullable', Rule::in(Expense::STATUSES)],
            'category' => ['nullable', Rule::in(Expense::CATEGORIES)],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
        ]);

        $page = Expense::with('reviewer')
            ->where('user_id', $request->user()->id)
            ->when(
                $validated['status'] ?? null,
                fn ($query, $status) => $query->where('status', $status)
            )
            ->when(
                $validated['category'] ?? null,
                fn ($query, $category) => $query->where('category', $category)
            )
            ->orderByDesc('spent_at')
            ->paginate((int) ($validated['per_page'] ?? 30));

        return ApiResponse::success(
            collect($page->items())
                ->map(fn (Expense $expense) => $this->payload($expense))
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

    public function show(Request $request, Expense $expense): JsonResponse
    {
        abort_unless($request->user()->hasPermission('expenses:view'), 403);
        abort_unless((int) $expense->user_id === (int) $request->user()->id, 404);

        return ApiResponse::success($this->payload($expense->load('reviewer')));
    }

    private function payload(Expense $expense): array
    {
        return [
            'id' => $expense->uuid,
            'expense_number' => $expense->expense_number,
            'spent_at' => $expense->spent_at?->toISOString(),
            'category' => $expense->category,
            'currency' => $expense->currency,
            'amount' => (float) $expense->amount,
            'fuel_liters' => $expense->fuel_liters === null
                ? null
                : (float) $expense->fuel_liters,
            'fuel_unit_price' => $expense->fuel_unit_price === null
                ? null
                : (float) $expense->fuel_unit_price,
            'odometer_km' => $expense->odometer_km === null
                ? null
                : (float) $expense->odometer_km,
            'merchant' => $expense->merchant,
            'reference_number' => $expense->reference_number,
            'latitude' => (float) $expense->latitude,
            'longitude' => (float) $expense->longitude,
            'accuracy' => (float) $expense->accuracy,
            'status' => $expense->status,
            'notes' => $expense->notes,
            'review_note' => $expense->review_note,
            'reviewed_at' => $expense->reviewed_at?->toISOString(),
            'reviewed_by' => $expense->reviewer?->name,
        ];
    }
}
