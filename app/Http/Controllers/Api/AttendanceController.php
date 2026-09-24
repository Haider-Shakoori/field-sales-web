<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\WorkSessionResource;
use App\Models\WorkSession;
use App\Services\MileageService;
use App\Services\TenantClock;
use App\Services\TrackingSettingsService;
use App\Support\ApiResponse;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AttendanceController extends Controller
{
    public function start(Request $request, TenantClock $clock)
    {
        $validated = $request->validate([
            'latitude' => 'required|numeric|between:-90,90|not_in:0',
            'longitude' => 'required|numeric|between:-180,180|not_in:0',
            'accuracy' => 'required|numeric|between:0,200',
            'offline_uuid' => 'required|uuid',
            'started_at' => 'nullable|date',
            'vehicle_reference' => 'nullable|string|max:120',
            'odometer_start_km' => 'nullable|numeric|min:0|max:999999999.99',
        ]);

        $user = $request->user()->load(['tenant', 'salesman']);
        $device = $request->attributes->get('device');

        $existing = WorkSession::where('tenant_id', $user->tenant_id)
            ->where('uuid', $validated['offline_uuid'])
            ->first();

        if ($existing) {
            if ((int) $existing->user_id !== (int) $user->id) {
                return ApiResponse::error(
                    'This attendance UUID is already in use.',
                    409,
                    null,
                    'SESSION_UUID_CONFLICT',
                );
            }

            return ApiResponse::success(
                (new WorkSessionResource($existing->load(['user', 'device'])))->resolve(),
                200,
            );
        }

        $startedAt = isset($validated['started_at'])
            ? CarbonImmutable::parse($validated['started_at'])->utc()
            : now()->toImmutable();

        if ($startedAt->gt(now()->addMinutes(5))) {
            return ApiResponse::error(
                'Start time is too far in the future.',
                422,
                ['started_at' => ['Start time may not be more than five minutes in the future.']],
                'VALIDATION_ERROR',
            );
        }

        $localDate = $clock->localDate($user->tenant, $startedAt);

        $conflicting = WorkSession::where('tenant_id', $user->tenant_id)
            ->where('user_id', $user->id)
            ->whereDate('date', $localDate)
            ->first();

        if ($conflicting) {
            return ApiResponse::error(
                'A work session already exists for this local date.',
                409,
                [
                    'session' => (new WorkSessionResource(
                        $conflicting->load(['user', 'device'])
                    ))->resolve(),
                ],
                'SESSION_ALREADY_EXISTS',
            );
        }

        $settings = app(TrackingSettingsService::class)->get($user->tenant);
        $localStartedAt = $startedAt->setTimezone($settings['timezone']);

        $session = DB::transaction(function () use (
            $user,
            $device,
            $validated,
            $startedAt,
            $localDate,
            $settings,
            $localStartedAt
        ) {
            return WorkSession::create([
                'uuid' => $validated['offline_uuid'],
                'tenant_id' => $user->tenant_id,
                'user_id' => $user->id,
                'salesman_id' => $user->salesman->id,
                'device_id' => $device->id,
                'date' => $localDate,
                'start_time' => $startedAt,
                'start_latitude' => $validated['latitude'],
                'start_longitude' => $validated['longitude'],
                'start_accuracy' => $validated['accuracy'],
                'status' => 'active',
                'is_late_start' => $localStartedAt->format('H:i') > $settings['workday_start_time'],
                'vehicle_reference' => $validated['vehicle_reference'] ?? null,
                'odometer_start_km' => $validated['odometer_start_km'] ?? null,
            ]);
        });

        return ApiResponse::success(
            (new WorkSessionResource($session->load(['user', 'device'])))->resolve(),
            201,
        );
    }

    public function end(Request $request, MileageService $mileage)
    {
        $validated = $request->validate([
            'latitude' => 'required|numeric|between:-90,90|not_in:0',
            'longitude' => 'required|numeric|between:-180,180|not_in:0',
            'accuracy' => 'required|numeric|between:0,200',
            'ended_at' => 'nullable|date',
            'vehicle_reference' => 'nullable|string|max:120',
            'odometer_end_km' => 'nullable|numeric|min:0|max:999999999.99',
        ]);

        $user = $request->user()->load('tenant');

        $session = WorkSession::where('tenant_id', $user->tenant_id)
            ->where('user_id', $user->id)
            ->latest('start_time')
            ->first();

        if (! $session) {
            return ApiResponse::error('No work session found.', 404, null, 'SESSION_NOT_FOUND');
        }

        if ($session->status === 'completed') {
            if ($session->gps_distance_km === null) {
                $session = $mileage->refreshCompletedSession($session);
            }

            return ApiResponse::success(
                (new WorkSessionResource($session->load(['user', 'device'])))->resolve(),
            );
        }

        $endedAt = isset($validated['ended_at'])
            ? CarbonImmutable::parse($validated['ended_at'])->utc()
            : now()->toImmutable();

        if ($endedAt->gt(now()->addMinutes(5))) {
            return ApiResponse::error(
                'End time is too far in the future.',
                422,
                ['ended_at' => ['End time may not be more than five minutes in the future.']],
                'VALIDATION_ERROR',
            );
        }

        if ($endedAt->lt($session->start_time)) {
            return ApiResponse::error(
                'End time cannot be before the work session start time.',
                422,
                ['ended_at' => ['End time must be on or after the work session start time.']],
                'VALIDATION_ERROR',
            );
        }

        if (
            isset($validated['odometer_end_km'])
            && $session->odometer_start_km !== null
            && (float) $validated['odometer_end_km'] < (float) $session->odometer_start_km
        ) {
            return ApiResponse::error(
                'End odometer cannot be below the start odometer.',
                422,
                [
                    'odometer_end_km' => [
                        'End odometer must be greater than or equal to the start odometer.',
                    ],
                ],
                'VALIDATION_ERROR',
            );
        }

        $settings = app(TrackingSettingsService::class)->get($user->tenant);
        $localEndedAt = $endedAt->setTimezone($settings['timezone']);

        $session->update([
            'end_time' => $endedAt,
            'end_latitude' => $validated['latitude'],
            'end_longitude' => $validated['longitude'],
            'end_accuracy' => $validated['accuracy'],
            'status' => 'completed',
            'duration_minutes' => $session->start_time->diffInMinutes($endedAt),
            'is_early_finish' => $localEndedAt->format('H:i') < $settings['workday_end_time'],
            'vehicle_reference' => $validated['vehicle_reference']
                ?? $session->vehicle_reference,
            'odometer_end_km' => $validated['odometer_end_km'] ?? null,
        ]);

        $session = $mileage->refreshCompletedSession($session->fresh());

        return ApiResponse::success(
            (new WorkSessionResource($session->load(['user', 'device'])))->resolve(),
        );
    }

    public function today(Request $request, TenantClock $clock)
    {
        $user = $request->user()->load('tenant');
        $date = $clock->now($user->tenant)->toDateString();

        $session = WorkSession::where('tenant_id', $user->tenant_id)
            ->where('user_id', $user->id)
            ->whereDate('date', $date)
            ->first();

        return ApiResponse::success(
            $session
                ? (new WorkSessionResource($session->load(['user', 'device'])))->resolve()
                : null,
        );
    }

    public function history(Request $request)
    {
        $perPage = min(100, max(1, (int) $request->integer('per_page', 20)));

        $paginator = WorkSession::where('tenant_id', $request->user()->tenant_id)
            ->where('user_id', $request->user()->id)
            ->with(['user', 'device'])
            ->latest('date')
            ->paginate($perPage);

        return ApiResponse::success(
            WorkSessionResource::collection($paginator->items())->resolve(),
            200,
            [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        );
    }
}
