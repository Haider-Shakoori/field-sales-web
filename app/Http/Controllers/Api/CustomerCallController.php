<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerCallActivity;
use App\Models\CustomerVisit;
use App\Models\WorkSession;
use App\Support\ApiResponse;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class CustomerCallController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'offline_uuid' => ['required', 'uuid'],
            'customer_uuid' => ['required', 'uuid'],
            'visit_uuid' => ['nullable', 'uuid'],
            'phone_number' => ['required', 'string', 'max:50'],
            'initiated_at' => ['required', 'date'],
            'outcome' => ['nullable', 'in:answered,no_answer,busy,call_back_later,order_discussion,payment_follow_up,other'],
            'notes' => ['nullable', 'string', 'max:3000'],
        ]);

        $user = $request->user()->load('salesman');
        $existing = CustomerCallActivity::where('tenant_id', $user->tenant_id)
            ->where('uuid', $validated['offline_uuid'])
            ->first();

        if ($existing) {
            if ((int) $existing->user_id !== (int) $user->id) {
                return ApiResponse::error('Call identifier conflict.', 409, null, 'OFFLINE_UUID_CONFLICT');
            }
            return ApiResponse::success($existing, 200);
        }

        $customer = Customer::where('tenant_id', $user->tenant_id)
            ->where('uuid', $validated['customer_uuid'])
            ->first();

        if (! $customer) {
            return ApiResponse::error('Customer not found.', 404, null, 'CUSTOMER_NOT_FOUND');
        }

        $initiatedAt = CarbonImmutable::parse($validated['initiated_at'])->utc();
        if ($initiatedAt->gt(CarbonImmutable::now('UTC')->addMinutes(5))) {
            return ApiResponse::error('Call time is too far in the future.', 422, null, 'INVALID_EVENT_TIME');
        }

        $visitId = null;
        if (! empty($validated['visit_uuid'])) {
            $visitId = CustomerVisit::where('tenant_id', $user->tenant_id)
                ->where('uuid', $validated['visit_uuid'])
                ->value('id');
        }

        $workSessionId = WorkSession::where('tenant_id', $user->tenant_id)
            ->where('user_id', $user->id)
            ->where('start_time', '<=', $initiatedAt)
            ->where(function ($query) use ($initiatedAt) {
                $query->whereNull('end_time')->orWhere('end_time', '>=', $initiatedAt);
            })
            ->latest('start_time')
            ->value('id');

        $call = CustomerCallActivity::create([
            'uuid' => $validated['offline_uuid'],
            'tenant_id' => $user->tenant_id,
            'customer_id' => $customer->id,
            'user_id' => $user->id,
            'salesman_id' => $user->salesman?->id,
            'work_session_id' => $workSessionId,
            'visit_id' => $visitId,
            'phone_number' => $validated['phone_number'],
            'initiated_at' => $initiatedAt,
            'outcome' => $validated['outcome'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        return ApiResponse::success($call, 201);
    }

    public function activity(Request $request, string $customerUuid)
    {
        $customer = Customer::where('tenant_id', $request->user()->tenant_id)
            ->where('uuid', $customerUuid)
            ->firstOrFail();

        $visits = $customer->visits()
            ->latest('checked_in_at')
            ->limit(100)
            ->get()
            ->map(fn ($visit) => [
                'type' => 'visit',
                'at' => $visit->checked_in_at?->toISOString(),
                'data' => $visit,
            ]);

        $calls = $customer->calls()
            ->latest('initiated_at')
            ->limit(100)
            ->get()
            ->map(fn ($call) => [
                'type' => 'call',
                'at' => $call->initiated_at?->toISOString(),
                'data' => $call,
            ]);

        return ApiResponse::success(
            $visits->concat($calls)->sortByDesc('at')->values()
        );
    }
}
