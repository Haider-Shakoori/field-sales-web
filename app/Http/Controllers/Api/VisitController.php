<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerVisit;
use App\Models\SalesRoute;
use App\Models\WorkSession;
use App\Services\GeoService;
use App\Services\TenantClock;
use App\Support\ApiResponse;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class VisitController extends Controller
{
    public function today(Request $request, TenantClock $clock)
    {
        $user = $request->user()->load(['tenant', 'salesman']);
        abort_unless($user->salesman, 422);

        $date = $clock->now($user->tenant)->toDateString();

        $customers = Customer::where('tenant_id', $user->tenant_id)
            ->where('assigned_salesman_id', $user->salesman->id)
            ->where('is_active', true)
            ->orderBy('route_id')
            ->orderBy('name')
            ->get();

        $visits = CustomerVisit::where('tenant_id', $user->tenant_id)
            ->where('salesman_id', $user->salesman->id)
            ->whereBetween('checked_in_at', $clock->dayBoundsUtc($user->tenant, $date))
            ->get()
            ->keyBy('customer_id');

        return ApiResponse::success($customers->map(function (Customer $customer) use ($visits) {
            $visit = $visits->get($customer->id);

            return [
                'customer' => $customer,
                'visit' => $visit,
                'status' => $visit?->status ?? 'pending',
            ];
        })->values());
    }

    public function checkIn(Request $request, GeoService $geo)
    {
        $validated = $request->validate([
            'offline_uuid' => ['required', 'uuid'],
            'customer_uuid' => ['required', 'uuid'],
            'route_uuid' => ['nullable', 'uuid'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['required', 'numeric', 'between:0,200'],
            'checked_in_at' => ['nullable', 'date'],
            'is_planned' => ['nullable', 'boolean'],
        ]);

        $user = $request->user()->load(['tenant', 'salesman']);
        if (! $user->salesman) {
            return ApiResponse::error('No salesman profile is linked to this user.', 422, null, 'SALESMAN_REQUIRED');
        }

        $existing = CustomerVisit::where('tenant_id', $user->tenant_id)
            ->where('uuid', $validated['offline_uuid'])
            ->first();

        if ($existing) {
            if ((int) $existing->user_id !== (int) $user->id) {
                return ApiResponse::error('Visit identifier conflict.', 409, null, 'OFFLINE_UUID_CONFLICT');
            }

            return ApiResponse::success($this->payload($existing->load('customer')), 200);
        }

        $customer = Customer::where('tenant_id', $user->tenant_id)
            ->where('uuid', $validated['customer_uuid'])
            ->where('is_active', true)
            ->first();

        if (! $customer) {
            return ApiResponse::error('Customer not found.', 404, null, 'CUSTOMER_NOT_FOUND');
        }

        if ($customer->assigned_salesman_id && (int) $customer->assigned_salesman_id !== (int) $user->salesman->id
            && ! $user->hasAnyRole(['company_admin', 'sales_manager'])) {
            return ApiResponse::error('Customer is not assigned to this salesman.', 403, null, 'CUSTOMER_NOT_ASSIGNED');
        }

        if ((float) $validated['latitude'] === 0.0 && (float) $validated['longitude'] === 0.0) {
            return ApiResponse::error('A usable visit location is required.', 422, null, 'INVALID_LOCATION');
        }

        $checkedInAt = isset($validated['checked_in_at'])
            ? CarbonImmutable::parse($validated['checked_in_at'])->utc()
            : CarbonImmutable::now('UTC');

        if ($checkedInAt->gt(CarbonImmutable::now('UTC')->addMinutes(5))) {
            return ApiResponse::error('Check-in time is too far in the future.', 422, null, 'INVALID_EVENT_TIME');
        }

        $session = WorkSession::where('tenant_id', $user->tenant_id)
            ->where('user_id', $user->id)
            ->where('start_time', '<=', $checkedInAt)
            ->where(function ($query) use ($checkedInAt) {
                $query->whereNull('end_time')->orWhere('end_time', '>=', $checkedInAt);
            })
            ->latest('start_time')
            ->first();

        if (! $session) {
            return ApiResponse::error('Start the work day before checking in.', 409, null, 'NO_WORK_SESSION');
        }

        $active = CustomerVisit::where('tenant_id', $user->tenant_id)
            ->where('user_id', $user->id)
            ->where('status', 'checked_in')
            ->first();

        if ($active) {
            return ApiResponse::error('Another customer visit is already active.', 409, [
                'active_visit_uuid' => $active->uuid,
            ], 'ACTIVE_VISIT_EXISTS');
        }

        $distance = null;
        $withinGeofence = false;

        if ($customer->latitude !== null && $customer->longitude !== null) {
            $distance = $geo->distanceMeters(
                (float) $validated['latitude'],
                (float) $validated['longitude'],
                (float) $customer->latitude,
                (float) $customer->longitude
            );
            $withinGeofence = $distance <= (int) $customer->geofence_radius;
        }

        $routeId = $customer->route_id;
        if (! empty($validated['route_uuid'])) {
            $routeId = SalesRoute::where('tenant_id', $user->tenant_id)
                ->where('uuid', $validated['route_uuid'])
                ->value('id') ?? $routeId;
        }

        $visit = CustomerVisit::create([
            'uuid' => $validated['offline_uuid'],
            'tenant_id' => $user->tenant_id,
            'customer_id' => $customer->id,
            'user_id' => $user->id,
            'salesman_id' => $user->salesman->id,
            'work_session_id' => $session->id,
            'route_id' => $routeId,
            'is_planned' => $validated['is_planned'] ?? ($customer->route_id !== null),
            'status' => 'checked_in',
            'checked_in_at' => $checkedInAt,
            'check_in_latitude' => $validated['latitude'],
            'check_in_longitude' => $validated['longitude'],
            'check_in_accuracy' => $validated['accuracy'],
            'distance_from_customer_m' => $distance,
            'within_geofence' => $withinGeofence,
            'flags' => $withinGeofence || $distance === null ? [] : ['outside_geofence'],
        ]);

        return ApiResponse::success($this->payload($visit->load('customer')), 201);
    }

    public function checkOut(Request $request, CustomerVisit $visit)
    {
        abort_unless((int) $visit->tenant_id === (int) $request->user()->tenant_id, 404);
        abort_unless((int) $visit->user_id === (int) $request->user()->id || $request->user()->hasAnyRole(['company_admin', 'sales_manager']), 403);

        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['required', 'numeric', 'between:0,200'],
            'checked_out_at' => ['nullable', 'date'],
            'outcome' => ['required', 'in:order_placed,collection_made,complaint,no_stock_needed,shop_closed,customer_unavailable,follow_up,other'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'media' => ['nullable', 'array'],
        ]);

        if ($visit->status === 'completed') {
            return ApiResponse::success($this->payload($visit->load('customer')));
        }

        $checkedOutAt = isset($validated['checked_out_at'])
            ? CarbonImmutable::parse($validated['checked_out_at'])->utc()
            : CarbonImmutable::now('UTC');

        if ($checkedOutAt->gt(CarbonImmutable::now('UTC')->addMinutes(5))
            || $checkedOutAt->lt(CarbonImmutable::parse($visit->checked_in_at)->utc())) {
            return ApiResponse::error('Invalid check-out time.', 422, null, 'INVALID_EVENT_TIME');
        }

        $duration = CarbonImmutable::parse($visit->checked_in_at)->utc()->diffInMinutes($checkedOutAt);
        $flags = collect($visit->flags ?? []);
        if ($duration < 1) {
            $flags->push('very_short_visit');
        }

        $visit->update([
            'status' => 'completed',
            'checked_out_at' => $checkedOutAt,
            'check_out_latitude' => $validated['latitude'],
            'check_out_longitude' => $validated['longitude'],
            'check_out_accuracy' => $validated['accuracy'],
            'duration_minutes' => $duration,
            'outcome' => $validated['outcome'],
            'notes' => $validated['notes'] ?? null,
            'media' => $validated['media'] ?? $visit->media,
            'flags' => $flags->unique()->values()->all(),
        ]);

        return ApiResponse::success($this->payload($visit->fresh()->load('customer')));
    }

    public function history(Request $request)
    {
        $user = $request->user()->load('salesman');
        $query = CustomerVisit::with('customer:id,uuid,code,name,shop_name')
            ->where('tenant_id', $user->tenant_id);

        if ($user->role === 'salesman' && $user->salesman) {
            $query->where('salesman_id', $user->salesman->id);
        }

        if ($request->filled('customer_uuid')) {
            $customerId = Customer::where('tenant_id', $user->tenant_id)
                ->where('uuid', $request->string('customer_uuid'))
                ->value('id');
            $query->where('customer_id', $customerId ?: 0);
        }

        return ApiResponse::success(
            $query->latest('checked_in_at')->limit(200)->get()->map(fn ($visit) => $this->payload($visit))->values()
        );
    }

    private function payload(CustomerVisit $visit): array
    {
        return [
            'id' => $visit->id,
            'uuid' => $visit->uuid,
            'customer_id' => $visit->customer_id,
            'customer_uuid' => $visit->customer?->uuid,
            'customer_name' => $visit->customer?->shop_name ?: $visit->customer?->name,
            'route_id' => $visit->route_id,
            'is_planned' => $visit->is_planned,
            'status' => $visit->status,
            'checked_in_at' => $visit->checked_in_at?->toISOString(),
            'checked_out_at' => $visit->checked_out_at?->toISOString(),
            'within_geofence' => $visit->within_geofence,
            'distance_from_customer_m' => $visit->distance_from_customer_m !== null ? round((float) $visit->distance_from_customer_m, 1) : null,
            'duration_minutes' => $visit->duration_minutes,
            'outcome' => $visit->outcome,
            'notes' => $visit->notes,
            'flags' => $visit->flags ?? [],
        ];
    }
}
