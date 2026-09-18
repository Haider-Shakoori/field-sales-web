<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\WorkSessionResource;
use App\Models\WorkSession;
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
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['required', 'numeric', 'between:0,200'],
            'offline_uuid' => ['required', 'uuid'],
            'started_at' => ['nullable', 'date'],
        ]);

        if ((float) $validated['latitude'] === 0.0 && (float) $validated['longitude'] === 0.0) {
            return ApiResponse::error(
                'A usable start location is required.',
                422,
                ['latitude' => ['Latitude and longitude cannot both be zero.']],
                'VALIDATION_ERROR'
            );
        }

        $user = $request->user()->load(['tenant', 'salesman']);
        $device = $request->attributes->get('device');

        if (! $user->salesman) {
            return ApiResponse::error('No salesman profile is linked to this user.', 422, null, 'SALESMAN_REQUIRED');
        }

        $existingUuid = WorkSession::where('tenant_id', $user->tenant_id)
            ->where('uuid', $validated['offline_uuid'])
            ->first();

        if ($existingUuid) {
            if ((int) $existingUuid->user_id !== (int) $user->id) {
                return ApiResponse::error(
                    'This offline attendance identifier already belongs to another user.',
                    409,
                    null,
                    'OFFLINE_UUID_CONFLICT'
                );
            }

            return ApiResponse::success(
                (new WorkSessionResource($existingUuid->load(['user', 'device'])))->resolve(),
                200
            );
        }

        $start = isset($validated['started_at'])
            ? CarbonImmutable::parse($validated['started_at'])->utc()
            : CarbonImmutable::now('UTC');

        if ($start->gt(CarbonImmutable::now('UTC')->addMinutes(5))) {
            return ApiResponse::error(
                'Start time is too far in the future.',
                422,
                ['started_at' => ['The start time cannot be more than five minutes in the future.']],
                'VALIDATION_ERROR'
            );
        }

        $date = $clock->localDate($user->tenant, $start);

        if (WorkSession::where('tenant_id', $user->tenant_id)
            ->where('user_id', $user->id)
            ->whereDate('date', $date)
            ->exists()) {
            return ApiResponse::error(
                'A work session already exists for this local date.',
                409,
                null,
                'SESSION_ALREADY_EXISTS'
            );
        }

        $settings = app(TrackingSettingsService::class)->get($user->tenant);
        $localStart = $start->setTimezone($settings['timezone']);

        $session = DB::transaction(function () use ($user, $device, $validated, $start, $date, $settings, $localStart) {
            return WorkSession::create([
                'uuid' => $validated['offline_uuid'],
                'tenant_id' => $user->tenant_id,
                'user_id' => $user->id,
                'salesman_id' => $user->salesman->id,
                'device_id' => $device->id,
                'date' => $date,
                'start_time' => $start,
                'start_latitude' => $validated['latitude'],
                'start_longitude' => $validated['longitude'],
                'start_accuracy' => $validated['accuracy'],
                'status' => 'active',
                'is_late_start' => $localStart->format('H:i') > $settings['workday_start_time'],
            ]);
        });

        return ApiResponse::success(
            (new WorkSessionResource($session->load(['user', 'device'])))->resolve(),
            201
        );
    }

    public function end(Request $request)
    {
        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['required', 'numeric', 'between:0,200'],
            'ended_at' => ['nullable', 'date'],
        ]);

        if ((float) $validated['latitude'] === 0.0 && (float) $validated['longitude'] === 0.0) {
            return ApiResponse::error(
                'A usable end location is required.',
                422,
                ['latitude' => ['Latitude and longitude cannot both be zero.']],
                'VALIDATION_ERROR'
            );
        }

        $user = $request->user()->load('tenant');

        $session = WorkSession::where('tenant_id', $user->tenant_id)
            ->where('user_id', $user->id)
            ->latest('start_time')
            ->first();

        if (! $session) {
            return ApiResponse::error('No work session found.', 404, null, 'SESSION_NOT_FOUND');
        }

        if ($session->status === 'completed') {
            return ApiResponse::success(
                (new WorkSessionResource($session->load(['user', 'device'])))->resolve()
            );
        }

        $end = isset($validated['ended_at'])
            ? CarbonImmutable::parse($validated['ended_at'])->utc()
            : CarbonImmutable::now('UTC');

        if ($end->gt(CarbonImmutable::now('UTC')->addMinutes(5))) {
            return ApiResponse::error(
                'End time is too far in the future.',
                422,
                ['ended_at' => ['The end time cannot be more than five minutes in the future.']],
                'VALIDATION_ERROR'
            );
        }

        if ($end->lt(CarbonImmutable::parse($session->start_time)->utc())) {
            return ApiResponse::error(
                'End time cannot be before the work session start time.',
                422,
                ['ended_at' => ['The end time must be on or after the start time.']],
                'VALIDATION_ERROR'
            );
        }

        $settings = app(TrackingSettingsService::class)->get($user->tenant);
        $localEnd = $end->setTimezone($settings['timezone']);

        $session->update([
            'end_time' => $end,
            'end_latitude' => $validated['latitude'],
            'end_longitude' => $validated['longitude'],
            'end_accuracy' => $validated['accuracy'],
            'status' => 'completed',
            'duration_minutes' => CarbonImmutable::parse($session->start_time)->utc()->diffInMinutes($end),
            'is_early_finish' => $localEnd->format('H:i') < $settings['workday_end_time'],
        ]);

        return ApiResponse::success(
            (new WorkSessionResource($session->fresh()->load(['user', 'device'])))->resolve()
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
                : null
        );
    }

    public function history(Request $request)
    {
        $perPage = min(100, max(1, (int) $request->integer('per_page', 20)));

        $paginator = WorkSession::where('tenant_id', $request->user()->tenant_id)
            ->where('user_id', $request->user()->id)
            ->with(['user', 'device'])
            ->latest('date')
            ->latest('start_time')
            ->paginate($perPage);

        return ApiResponse::success(
            WorkSessionResource::collection($paginator->items())->resolve(),
            200,
            [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ]
        );
    }
}
