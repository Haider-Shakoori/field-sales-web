<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CurrentLocation;
use App\Models\LocationHistory;
use App\Models\LocationSyncBatch;
use App\Models\PrivacyAcknowledgement;
use App\Models\SalesmanAssignment;
use App\Models\User;
use App\Models\WorkSession;
use App\Services\TenantClock;
use App\Support\ApiResponse;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

class GpsController extends Controller
{
    public function ingest(Request $request, TenantClock $clock)
    {
        $request->validate([
            'batch_uuid' => 'required|uuid',
            'locations' => 'required|array|min:1|max:'.config('tenancy.tracking.max_batch_points', 100),
        ]);

        $user = $request->user()->load(['tenant', 'salesman']);
        $device = $request->attributes->get('device');

        if (! $user->salesman) {
            return ApiResponse::error(
                'A salesman profile is required for GPS tracking.',
                403,
                null,
                'SALESMAN_PROFILE_REQUIRED',
            );
        }

        $batchUuid = $request->string('batch_uuid')->toString();
        $submitted = $request->input('locations', []);

        $batch = LocationSyncBatch::firstOrCreate(
            [
                'tenant_id' => $user->tenant_id,
                'device_id' => $device->id,
                'user_id' => $user->id,
                'batch_uuid' => $batchUuid,
            ],
            [
                'point_count' => count($submitted),
                'received_at' => now(),
            ],
        );

        $candidateUuids = collect($submitted)
            ->pluck('client_uuid')
            ->filter(fn ($uuid) => is_string($uuid) && Str::isUuid($uuid))
            ->unique()
            ->values();

        $knownUuids = $candidateUuids->isEmpty()
            ? collect()
            : LocationHistory::query()
                ->where('tenant_id', $user->tenant_id)
                ->whereIn('client_uuid', $candidateUuids)
                ->pluck('client_uuid')
                ->flip();

        $accepted = [];
        $duplicates = [];
        $rejected = [];
        $rejectedDetails = [];
        $seenInBatch = [];
        $valid = [];

        foreach ($submitted as $index => $point) {
            $uuid = $point['client_uuid'] ?? null;

            $reject = function (string $code, string $reason) use (
                &$rejected,
                &$rejectedDetails,
                $index,
                $uuid
            ): void {
                if (is_string($uuid) && $uuid !== '') {
                    $rejected[] = $uuid;
                }

                $rejectedDetails[] = [
                    'index' => $index,
                    'client_uuid' => $uuid,
                    'code' => $code,
                    'reason' => $reason,
                ];
            };

            if (! is_string($uuid) || ! Str::isUuid($uuid)) {
                $reject('malformed_uuid', 'client_uuid must be a valid UUID.');

                continue;
            }

            if (isset($seenInBatch[$uuid]) || $knownUuids->has($uuid)) {
                $duplicates[] = $uuid;

                continue;
            }

            $seenInBatch[$uuid] = true;

            $latitude = $point['latitude'] ?? null;
            $longitude = $point['longitude'] ?? null;
            $accuracy = $point['accuracy'] ?? null;
            $speed = $point['speed'] ?? null;
            $heading = $point['heading'] ?? null;
            $battery = $point['battery_level'] ?? null;
            $sequence = $point['sequence_number'] ?? null;
            $network = $point['network_status'] ?? null;
            $provider = $point['provider'] ?? null;

            if (! is_numeric($latitude)
                || ! is_numeric($longitude)
                || (float) $latitude < -90
                || (float) $latitude > 90
                || (float) $longitude < -180
                || (float) $longitude > 180
                || ((float) $latitude === 0.0 && (float) $longitude === 0.0)) {
                $reject('invalid_coordinates', 'Latitude/longitude are outside the accepted range.');

                continue;
            }

            if (! is_numeric($accuracy) || (float) $accuracy < 0 || (float) $accuracy > 200) {
                $reject('low_accuracy', 'accuracy must be between 0 and 200 metres.');

                continue;
            }

            if ($speed !== null && (! is_numeric($speed) || (float) $speed < 0 || (float) $speed > 55)) {
                $reject('invalid_speed', 'speed must be between 0 and 55 m/s.');

                continue;
            }

            if ($heading !== null && (! is_numeric($heading) || (float) $heading < 0 || (float) $heading > 360)) {
                $reject('invalid_heading', 'heading must be between 0 and 360 degrees.');

                continue;
            }

            if ($battery !== null && (! is_numeric($battery) || (int) $battery < 0 || (int) $battery > 100)) {
                $reject('invalid_battery', 'battery_level must be between 0 and 100.');

                continue;
            }

            if ($sequence !== null && (filter_var($sequence, FILTER_VALIDATE_INT) === false || (int) $sequence < 0)) {
                $reject('invalid_sequence', 'sequence_number must be a non-negative integer.');

                continue;
            }

            if ($network !== null && (! is_string($network) || mb_strlen($network) > 20)) {
                $reject('invalid_network', 'network_status may not exceed 20 characters.');

                continue;
            }

            if ($provider !== null && (! is_string($provider) || mb_strlen($provider) > 50)) {
                $reject('invalid_provider', 'provider may not exceed 50 characters.');

                continue;
            }

            if (! isset($point['recorded_at']) || ! is_string($point['recorded_at']) || trim($point['recorded_at']) === '') {
                $reject('invalid_timestamp', 'recorded_at must be a valid timestamp.');

                continue;
            }

            try {
                $recordedAt = CarbonImmutable::parse($point['recorded_at'])->utc();
            } catch (\Throwable) {
                $reject('invalid_timestamp', 'recorded_at must be a valid timestamp.');

                continue;
            }

            if ($recordedAt->gt(now()->addMinutes(5))) {
                $reject('future_timestamp', 'recorded_at may not be more than five minutes in the future.');

                continue;
            }

            $localDate = $clock->localDate($user->tenant, $recordedAt);
            $hasWorkSession = WorkSession::query()
                ->where('tenant_id', $user->tenant_id)
                ->where('user_id', $user->id)
                ->where(function ($query) use ($localDate, $recordedAt): void {
                    $query->whereDate('date', $localDate)
                        ->orWhere(function ($spanning) use ($recordedAt): void {
                            $spanning->where('start_time', '<=', $recordedAt)
                                ->where(function ($end) use ($recordedAt): void {
                                    $end->whereNull('end_time')
                                        ->orWhere('end_time', '>=', $recordedAt);
                                });
                        });
                })
                ->exists();

            if (! $hasWorkSession) {
                $reject('no_work_session', 'No work session covers this tenant-local date/time.');

                continue;
            }

            $valid[] = [
                'uuid' => $uuid,
                'latitude' => (float) $latitude,
                'longitude' => (float) $longitude,
                'accuracy' => (float) $accuracy,
                'altitude' => isset($point['altitude']) && is_numeric($point['altitude'])
                    ? (float) $point['altitude']
                    : null,
                'speed' => $speed === null ? null : (float) $speed,
                'heading' => $heading === null ? null : (float) $heading,
                'battery_level' => $battery === null ? null : (int) $battery,
                'is_charging' => (bool) ($point['is_charging'] ?? false),
                'network_status' => $network,
                'is_mock_location' => (bool) ($point['is_mock_location'] ?? false),
                'provider' => $provider,
                'recorded_at' => $recordedAt,
                'sequence_number' => $sequence === null ? null : (int) $sequence,
            ];
        }

        $newestCurrent = null;

        DB::transaction(function () use (
            $user,
            $device,
            $batch,
            $valid,
            &$accepted,
            &$newestCurrent
        ): void {
            foreach ($valid as $point) {
                $row = LocationHistory::create([
                    'tenant_id' => $user->tenant_id,
                    'user_id' => $user->id,
                    'salesman_id' => $user->salesman->id,
                    'device_id' => $device->id,
                    'client_uuid' => $point['uuid'],
                    'latitude' => $point['latitude'],
                    'longitude' => $point['longitude'],
                    'horizontal_accuracy' => $point['accuracy'],
                    'altitude' => $point['altitude'],
                    'speed' => $point['speed'],
                    'heading' => $point['heading'],
                    'battery_level' => $point['battery_level'],
                    'is_charging' => $point['is_charging'],
                    'network_status' => $point['network_status'],
                    'is_mock_location' => $point['is_mock_location'],
                    'provider' => $point['provider'],
                    'recorded_at' => $point['recorded_at'],
                    'received_at' => now(),
                    'sync_batch_id' => $batch->id,
                    'sequence_number' => $point['sequence_number'],
                    'metadata' => null,
                ]);

                $accepted[] = $point['uuid'];

                if ($newestCurrent === null
                    || $point['recorded_at']->gt($newestCurrent['recorded_at'])) {
                    $newestCurrent = [
                        ...$point,
                        'location_history_id' => $row->id,
                    ];
                }
            }

            if ($newestCurrent !== null) {
                $current = CurrentLocation::query()
                    ->where('tenant_id', $user->tenant_id)
                    ->where('user_id', $user->id)
                    ->lockForUpdate()
                    ->first();

                if (! $current || $newestCurrent['recorded_at']->gt($current->recorded_at)) {
                    CurrentLocation::updateOrCreate(
                        [
                            'tenant_id' => $user->tenant_id,
                            'user_id' => $user->id,
                        ],
                        [
                            'salesman_id' => $user->salesman->id,
                            'device_id' => $device->id,
                            'location_history_id' => $newestCurrent['location_history_id'],
                            'latitude' => $newestCurrent['latitude'],
                            'longitude' => $newestCurrent['longitude'],
                            'horizontal_accuracy' => $newestCurrent['accuracy'],
                            'altitude' => $newestCurrent['altitude'],
                            'speed' => $newestCurrent['speed'],
                            'heading' => $newestCurrent['heading'],
                            'battery_level' => $newestCurrent['battery_level'],
                            'is_charging' => $newestCurrent['is_charging'],
                            'network_status' => $newestCurrent['network_status'],
                            'is_mock_location' => $newestCurrent['is_mock_location'],
                            'provider' => $newestCurrent['provider'],
                            'recorded_at' => $newestCurrent['recorded_at'],
                            'received_at' => now(),
                        ],
                    );
                }
            }

            $batch->update([
                'processed_at' => now(),
            ]);
        });

        if ($newestCurrent !== null) {
            $this->cacheLatest($user->tenant_id, $user->id);
        }

        return ApiResponse::success([
            'accepted' => count($accepted),
            'rejected' => count($rejected),
            'duplicates' => count($duplicates),
            'batch_id' => $batch->id,
            'accepted_uuids' => array_values($accepted),
            'duplicate_uuids' => array_values(array_unique($duplicates)),
            'rejected_uuids' => array_values(array_unique($rejected)),
            'rejected_details' => $rejectedDetails,
        ], 201);
    }

    public function current(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'nullable|uuid',
        ]);

        $target = $this->targetUser($request, $validated['user_id'] ?? null);
        $cached = $this->readCachedLatest($request->user()->tenant_id, $target->id);

        if ($cached !== null) {
            return ApiResponse::success($cached);
        }

        $current = CurrentLocation::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->where('user_id', $target->id)
            ->first();

        return ApiResponse::success($current ? $this->locationPayload($current) : null);
    }

    public function history(Request $request, TenantClock $clock)
    {
        $validated = $request->validate([
            'date' => 'nullable|date_format:Y-m-d',
            'user_id' => 'nullable|uuid',
        ]);

        $target = $this->targetUser($request, $validated['user_id'] ?? null, $validated['date'] ?? null);
        $date = $validated['date'] ?? $clock->now($request->user()->tenant)->toDateString();
        $timezone = $clock->timezone($request->user()->tenant);
        $start = CarbonImmutable::parse($date.' 00:00:00', $timezone)->utc();
        $end = $start->addDay();

        $rows = LocationHistory::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->where('user_id', $target->id)
            ->where('recorded_at', '>=', $start)
            ->where('recorded_at', '<', $end)
            ->orderBy('recorded_at')
            ->get();

        $distance = 0.0;

        for ($index = 1; $index < $rows->count(); $index++) {
            $previous = $rows[$index - 1];
            $current = $rows[$index];

            if ((float) $previous->horizontal_accuracy > 50
                || (float) $current->horizontal_accuracy > 50) {

                continue;
            }

            $distance += $this->distance(
                (float) $previous->latitude,
                (float) $previous->longitude,
                (float) $current->latitude,
                (float) $current->longitude,
            );
        }

        $locations = $rows->map(fn ($row) => $this->locationPayload($row))->values();

        return ApiResponse::success([
            'locations' => $locations,
            'summary' => [
                'total_points' => $rows->count(),
                'distance_km' => round($distance, 3),
                'first_point' => $locations->first(),
                'last_point' => $locations->last(),
            ],
        ]);
    }

    public function acknowledge(Request $request)
    {
        $validated = $request->validate([
            'policy_version' => 'required|string|max:50',
            'acknowledged_at' => 'required|date',
            'app_version' => 'required|string|max:50',
        ]);

        $user = $request->user();
        $device = $request->attributes->get('device');

        $acknowledgement = PrivacyAcknowledgement::firstOrCreate(
            [
                'tenant_id' => $user->tenant_id,
                'user_id' => $user->id,
                'device_id' => $device->id,
                'policy_version' => $validated['policy_version'],
            ],
            [
                'uuid' => (string) Str::uuid(),
                'acknowledged_at' => $validated['acknowledged_at'],
                'app_version' => $validated['app_version'],
                'recorded_at' => now(),
            ],
        );

        return ApiResponse::success([
            'id' => $acknowledgement->id,
            'uuid' => $acknowledgement->uuid,
            'policy_version' => $acknowledgement->policy_version,
            'acknowledged_at' => $acknowledgement->acknowledged_at?->toISOString(),
            'recorded_at' => $acknowledgement->recorded_at?->toISOString(),
        ], $acknowledgement->wasRecentlyCreated ? 201 : 200);
    }

    private function targetUser(
        Request $request,
        ?string $requestedUserUuid = null,
        ?string $assignmentDate = null
    ): User {
        $actor = $request->user()->loadMissing(['supervisor']);
        $requestedUserUuid ??= $request->query('user_id');

        if (! $requestedUserUuid) {
            return $actor;
        }

        $target = User::query()
            ->where('tenant_id', $actor->tenant_id)
            ->where('uuid', $requestedUserUuid)
            ->with('salesman')
            ->firstOrFail();

        if ((int) $target->id === (int) $actor->id) {
            return $target;
        }

        abort_unless($actor->hasPermission('tracking:view'), 403);

        if ($actor->hasAnyRole(['supervisor'])) {
            abort_unless($actor->supervisor && $target->salesman, 403);

            $allowed = SalesmanAssignment::query()
                ->where('tenant_id', $actor->tenant_id)
                ->where('salesman_id', $target->salesman->id)
                ->where('supervisor_id', $actor->supervisor->id)
                ->current($assignmentDate ?: today())
                ->exists();

            abort_unless($allowed, 403);
        }

        return $target;
    }

    private function cacheLatest(int $tenantId, int $userId): void
    {
        try {
            $current = CurrentLocation::query()
                ->where('tenant_id', $tenantId)
                ->where('user_id', $userId)
                ->first();

            if (! $current) {
                return;
            }

            $key = "fs:{$tenantId}:locations:latest";
            Redis::hset($key, (string) $userId, json_encode($this->locationPayload($current)));
            Redis::expire($key, 3600);
        } catch (\Throwable $exception) {
            Log::warning('Latest GPS cache write failed.', [
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'exception' => $exception::class,
            ]);
        }
    }

    private function readCachedLatest(int $tenantId, int $userId): ?array
    {
        try {
            $value = Redis::hget("fs:{$tenantId}:locations:latest", (string) $userId);

            if (! is_string($value) || $value === '') {
                return null;
            }

            $decoded = json_decode($value, true);

            return is_array($decoded) ? $decoded : null;
        } catch (\Throwable $exception) {
            Log::warning('Latest GPS cache read failed.', [
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'exception' => $exception::class,
            ]);

            return null;
        }
    }

    private function locationPayload($location): array
    {
        return [
            'id' => $location->id,
            'latitude' => (float) $location->latitude,
            'longitude' => (float) $location->longitude,
            'accuracy' => (float) $location->horizontal_accuracy,
            'altitude' => $location->altitude === null ? null : (float) $location->altitude,
            'speed' => $location->speed === null ? null : (float) $location->speed,
            'heading' => $location->heading === null ? null : (float) $location->heading,
            'battery_level' => $location->battery_level === null ? null : (int) $location->battery_level,
            'is_charging' => (bool) $location->is_charging,
            'network_status' => $location->network_status,
            'is_mock_location' => (bool) $location->is_mock_location,
            'provider' => $location->provider,
            'recorded_at' => $location->recorded_at?->toISOString(),
            'received_at' => $location->received_at?->toISOString(),
        ];
    }

    private function distance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371;
        $deltaLatitude = deg2rad($lat2 - $lat1);
        $deltaLongitude = deg2rad($lon2 - $lon1);

        $a = sin($deltaLatitude / 2) ** 2
            + cos(deg2rad($lat1))
            * cos(deg2rad($lat2))
            * sin($deltaLongitude / 2) ** 2;

        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
