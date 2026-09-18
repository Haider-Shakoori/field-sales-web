<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Support\ApiResponse;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class ExpenseController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user()->load('salesman');
        $query = Expense::where('tenant_id', $user->tenant_id);

        if ($user->role === 'salesman' && $user->salesman) {
            $query->where('salesman_id', $user->salesman->id);
        }

        return ApiResponse::success($query->latest('spent_at')->limit(200)->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'offline_uuid' => ['required', 'uuid'],
            'category' => ['required', 'string', 'max:80'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'spent_at' => ['required', 'date'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'receipt_photo_path' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $user = $request->user()->load('salesman');
        $existing = Expense::where('tenant_id', $user->tenant_id)
            ->where('uuid', $validated['offline_uuid'])
            ->first();

        if ($existing) {
            return ApiResponse::success($existing, 200);
        }

        $spentAt = CarbonImmutable::parse($validated['spent_at'])->utc();
        if ($spentAt->gt(CarbonImmutable::now('UTC')->addMinutes(5))) {
            return ApiResponse::error('Expense time is too far in the future.', 422, null, 'INVALID_EVENT_TIME');
        }

        $row = Expense::create([
            'uuid' => $validated['offline_uuid'],
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'salesman_id' => $user->salesman->id,
            'category' => $validated['category'],
            'amount' => $validated['amount'],
            'currency' => strtoupper($validated['currency'] ?? 'AFN'),
            'spent_at' => $spentAt,
            'status' => 'submitted',
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'receipt_photo_path' => $validated['receipt_photo_path'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        return ApiResponse::success($row, 201);
    }
}
