<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerVisit;
use App\Models\VisitPhoto;
use App\Models\VisitSuspiciousFlag;
use App\Models\WorkSession;
use App\Services\DailyRoutePlannerService;
use App\Services\GeofenceService;
use App\Services\NotificationService;
use App\Services\VisitFormService;
use App\Support\ApiResponse;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class VisitController extends Controller
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function checkIn(
        Request $request,
        GeofenceService $geofence,
        DailyRoutePlannerService $planner,
    ): JsonResponse {
        $validated = $request->validate([
            'offline_uuid' => ['required', 'uuid'],
            'customer_id' => ['required', 'uuid'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['required', 'numeric', 'between:0,200'],
            'checked_in_at' => ['nullable', 'date'],
        ]);

        $user = $request->user()->load(['tenant', 'salesman']);
        abort_unless($user->salesman?->is_active, 403);

        $existing = CustomerVisit::where('uuid', $validated['offline_uuid'])->first();
        if ($existing) {
            abort_unless((int) $existing->user_id === (int) $user->id, 409);

            return ApiResponse::success($this->payload($existing->load($this->relations())));
        }

        $customer = Customer::where('uuid', $validated['customer_id'])->active()->firstOrFail();
        $checkedInAt = isset($validated['checked_in_at'])
            ? CarbonImmutable::parse($validated['checked_in_at'])->utc()
            : now()->toImmutable();

        if ($checkedInAt->gt(now()->addMinutes(5))) {
            return ApiResponse::error('Check-in time is too far in the future.', 422, null, 'VALIDATION_ERROR');
        }

        if ((float) $validated['latitude'] === 0.0 && (float) $validated['longitude'] === 0.0) {
            return ApiResponse::error('Invalid check-in coordinates.', 422, null, 'VALIDATION_ERROR');
        }

        $session = WorkSession::where('user_id', $user->id)
            ->where('start_time', '<=', $checkedInAt)
            ->where(function ($query) use ($checkedInAt): void {
                $query->whereNull('end_time')->orWhere('end_time', '>=', $checkedInAt);
            })
            ->latest('start_time')
            ->first();

        if (! $session) {
            return ApiResponse::error(
                'A visit must occur inside a work session.',
                409,
                null,
                'VISIT_OUTSIDE_WORK_SESSION'
            );
        }

        $localDate = $checkedInAt
            ->setTimezone($user->tenant->timezone)
            ->startOfDay();

        $planningContext = $planner->planningContextForCustomer(
            $user->salesman,
            $customer,
            $localDate,
        );
        $planned = $planningContext['planned'];
        $plannedRouteId = $planningContext['route_id'];

        $geo = $geofence->evaluate(
            $customer,
            (float) $validated['latitude'],
            (float) $validated['longitude'],
        );

        $device = $request->attributes->get('device');
        $otherActive = CustomerVisit::where('user_id', $user->id)
            ->where('status', 'active')
            ->exists();

        $visit = DB::transaction(function () use (
            $validated,
            $user,
            $customer,
            $device,
            $session,
            $planned,
            $plannedRouteId,
            $geo,
            $checkedInAt,
            $otherActive
        ) {
            $visit = CustomerVisit::create([
                'uuid' => $validated['offline_uuid'],
                'user_id' => $user->id,
                'salesman_id' => $user->salesman->id,
                'device_id' => $device->id,
                'customer_id' => $customer->id,
                'route_id' => $plannedRouteId,
                'work_session_id' => $session->id,
                'is_planned' => $planned,
                'status' => 'active',
                'checked_in_at' => $checkedInAt,
                'checkin_latitude' => $validated['latitude'],
                'checkin_longitude' => $validated['longitude'],
                'checkin_accuracy' => $validated['accuracy'],
                'checkin_distance_meters' => $geo['distance_meters'],
                'checkin_within_geofence' => $geo['within_geofence'],
            ]);

            if ($geo['within_geofence'] === false) {
                $this->flag($visit, 'location_mismatch', 'high', [
                    'phase' => 'check_in',
                    'distance_meters' => $geo['distance_meters'],
                    'radius_meters' => $customer->geofence_radius_meters,
                ]);
            }

            if ($otherActive) {
                $this->flag($visit, 'multiple_simultaneous_checkins', 'high');
            }

            return $visit;
        });

        return ApiResponse::success($this->payload($visit->load($this->relations())), 201);
    }

    public function checkOut(
        Request $request,
        CustomerVisit $visit,
        GeofenceService $geofence,
        VisitFormService $forms,
    ): JsonResponse {
        abort_unless((int) $visit->user_id === (int) $request->user()->id, 404);

        if ($visit->status === 'completed') {
            return ApiResponse::success($this->payload($visit->load($this->relations())));
        }

        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['required', 'numeric', 'between:0,200'],
            'checked_out_at' => ['nullable', 'date'],
            'outcome' => ['required', Rule::in(CustomerVisit::OUTCOMES)],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $checkedOutAt = isset($validated['checked_out_at'])
            ? CarbonImmutable::parse($validated['checked_out_at'])->utc()
            : now()->toImmutable();

        $missingForms = $forms->missingRequiredForVisit($visit);

        if ($missingForms->isNotEmpty()) {
            return ApiResponse::error(
                'Required visit forms must be submitted before checkout.',
                409,
                [
                    'forms' => $missingForms->map(fn ($template) => [
                        'id' => $template->uuid,
                        'code' => $template->code,
                        'name' => $template->name,
                    ])->values()->all(),
                ],
                'REQUIRED_VISIT_FORM_MISSING',
            );
        }

        if ($checkedOutAt->gt(now()->addMinutes(5)) || $checkedOutAt->lt($visit->checked_in_at)) {
            return ApiResponse::error('Invalid check-out time.', 422, null, 'VALIDATION_ERROR');
        }

        $visit->loadMissing('customer');
        $customer = $visit->customer;
        $geo = $geofence->evaluate(
            $customer,
            (float) $validated['latitude'],
            (float) $validated['longitude'],
        );
        $duration = (int) $visit->checked_in_at->diffInSeconds($checkedOutAt);

        DB::transaction(function () use ($visit, $validated, $checkedOutAt, $geo, $duration, $customer): void {
            $visit->update([
                'status' => 'completed',
                'outcome' => $validated['outcome'],
                'notes' => $validated['notes'] ?? null,
                'checked_out_at' => $checkedOutAt,
                'checkout_latitude' => $validated['latitude'],
                'checkout_longitude' => $validated['longitude'],
                'checkout_accuracy' => $validated['accuracy'],
                'checkout_distance_meters' => $geo['distance_meters'],
                'checkout_within_geofence' => $geo['within_geofence'],
                'duration_seconds' => $duration,
            ]);

            if ($geo['within_geofence'] === false) {
                $this->flag($visit, 'location_mismatch', 'high', [
                    'phase' => 'check_out',
                    'distance_meters' => $geo['distance_meters'],
                    'radius_meters' => $customer->geofence_radius_meters,
                ]);
            }

            if ($duration < 60) {
                $this->flag($visit, 'too_short_duration', 'medium', [
                    'duration_seconds' => $duration,
                    'threshold_seconds' => 60,
                ]);
            }
        });

        return ApiResponse::success($this->payload($visit->fresh()->load($this->relations())));
    }

    public function today(Request $request): JsonResponse
    {
        $user = $request->user()->load('tenant');
        $start = CarbonImmutable::now($user->tenant->timezone)->startOfDay()->utc();
        $end = $start->addDay();

        $visits = CustomerVisit::with($this->relations())
            ->where('user_id', $user->id)
            ->where('checked_in_at', '>=', $start)
            ->where('checked_in_at', '<', $end)
            ->orderByDesc('checked_in_at')
            ->get()
            ->map(fn (CustomerVisit $visit) => $this->payload($visit))
            ->values();

        return ApiResponse::success($visits);
    }

    public function history(Request $request): JsonResponse
    {
        $perPage = min(100, max(1, (int) $request->integer('per_page', 30)));
        $page = CustomerVisit::with($this->relations())
            ->where('user_id', $request->user()->id)
            ->orderByDesc('checked_in_at')
            ->paginate($perPage);

        return ApiResponse::success(
            collect($page->items())->map(fn (CustomerVisit $visit) => $this->payload($visit))->all(),
            200,
            [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        );
    }

    public function uploadPhoto(Request $request, CustomerVisit $visit): JsonResponse
    {
        abort_unless((int) $visit->user_id === (int) $request->user()->id, 404);

        $idempotency = $request->validate([
            'client_uuid' => ['required', 'uuid'],
        ]);

        $existing = VisitPhoto::where('uuid', $idempotency['client_uuid'])->first();

        if ($existing) {
            abort_unless(
                (int) $existing->visit_id === (int) $visit->id
                && (int) $existing->user_id === (int) $request->user()->id,
                409
            );

            return ApiResponse::success($this->photoPayload($existing));
        }

        $validated = $request->validate([
            'photo' => ['required', 'image', 'max:5120'],
            'captured_at' => ['nullable', 'date'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'accuracy' => ['nullable', 'numeric', 'between:0,200'],
        ]);

        $file = $request->file('photo');
        $path = $file->store('visits/'.$visit->uuid, 'public');

        $photo = VisitPhoto::create([
            'uuid' => $idempotency['client_uuid'],
            'visit_id' => $visit->id,
            'user_id' => $request->user()->id,
            'disk' => 'public',
            'path' => $path,
            'mime_type' => $file->getMimeType(),
            'size_bytes' => $file->getSize(),
            'captured_at' => $validated['captured_at'] ?? now(),
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'accuracy' => $validated['accuracy'] ?? null,
        ]);

        return ApiResponse::success($this->photoPayload($photo), 201);
    }

    private function flag(
        CustomerVisit $visit,
        string $reason,
        string $severity = 'medium',
        ?array $details = null,
    ): void {
        $existing = VisitSuspiciousFlag::where('visit_id', $visit->id)
            ->where('reason_code', $reason)
            ->first();

        if ($existing) {
            $existing->update([
                'severity' => $severity,
                'details' => array_filter([
                    'previous' => $existing->details,
                    'latest' => $details,
                ]),
            ]);

            return;
        }

        $flag = VisitSuspiciousFlag::create([
            'visit_id' => $visit->id,
            'reason_code' => $reason,
            'severity' => $severity,
            'details' => $details,
        ]);

        $this->notifications->notifySuspiciousVisit($flag);
    }

    private function relations(): array
    {
        return ['customer', 'route', 'photos', 'suspiciousFlags'];
    }

    private function payload(CustomerVisit $visit): array
    {
        return [
            'id' => $visit->uuid,
            'customer_id' => $visit->customer?->uuid,
            'customer_name' => $visit->customer?->name,
            'route_id' => $visit->route?->uuid,
            'is_planned' => $visit->is_planned,
            'status' => $visit->status,
            'outcome' => $visit->outcome,
            'notes' => $visit->notes,
            'checked_in_at' => $visit->checked_in_at?->toISOString(),
            'checked_out_at' => $visit->checked_out_at?->toISOString(),
            'duration_seconds' => $visit->duration_seconds,
            'checkin' => [
                'latitude' => (float) $visit->checkin_latitude,
                'longitude' => (float) $visit->checkin_longitude,
                'accuracy' => (float) $visit->checkin_accuracy,
                'distance_meters' => $visit->checkin_distance_meters === null ? null : (float) $visit->checkin_distance_meters,
                'within_geofence' => $visit->checkin_within_geofence,
            ],
            'checkout' => $visit->checked_out_at ? [
                'latitude' => (float) $visit->checkout_latitude,
                'longitude' => (float) $visit->checkout_longitude,
                'accuracy' => (float) $visit->checkout_accuracy,
                'distance_meters' => $visit->checkout_distance_meters === null ? null : (float) $visit->checkout_distance_meters,
                'within_geofence' => $visit->checkout_within_geofence,
            ] : null,
            'photos' => $visit->relationLoaded('photos')
                ? $visit->photos->map(fn (VisitPhoto $photo) => $this->photoPayload($photo))->values()->all()
                : [],
            'suspicious_flags' => $visit->relationLoaded('suspiciousFlags')
                ? $visit->suspiciousFlags->map(fn (VisitSuspiciousFlag $flag) => [
                    'id' => $flag->uuid,
                    'reason' => $flag->reason_code,
                    'severity' => $flag->severity,
                    'details' => $flag->details,
                    'reviewed_at' => $flag->reviewed_at?->toISOString(),
                ])->values()->all()
                : [],
        ];
    }

    private function photoPayload(VisitPhoto $photo): array
    {
        return [
            'id' => $photo->uuid,
            'url' => Storage::disk($photo->disk)->url($photo->path),
            'captured_at' => $photo->captured_at?->toISOString(),
            'latitude' => $photo->latitude === null ? null : (float) $photo->latitude,
            'longitude' => $photo->longitude === null ? null : (float) $photo->longitude,
        ];
    }
}
