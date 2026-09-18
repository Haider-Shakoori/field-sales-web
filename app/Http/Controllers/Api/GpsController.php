<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CurrentLocation;
use App\Models\LocationHistory;
use App\Models\LocationSyncBatch;
use App\Models\PrivacyAcknowledgement;
use App\Models\WorkSession;
use App\Services\TenantClock;
use App\Support\ApiResponse;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

class GpsController extends Controller
{
    public function ingest(Request $request)
    {
        $request->validate([
            'batch_uuid' => ['required', 'uuid'],
            'locations' => ['required', 'array', 'min:1', 'max:'.config('tenancy.tracking.max_batch_points', 100)],
        ]);

        $user = $request->user()->load(['tenant', 'salesman']);
        $device = $request->attributes->get('device');

        if (! $user->salesman) {
            return ApiResponse::error('No salesman profile is linked to this user.', 422, null, 'SALESMAN_REQUIRED');
        }

        $submitted = array_values($request->input('locations', []));
        $batchUuid = (string) $request->input('batch_uuid');

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
            ]
        );

        $submittedUuids = collect($submitted)
            ->pluck('client_uuid')
            ->filter(fn ($uuid) => is_string($uuid) && Str::isUuid($uuid))
            ->unique()
            ->values();

        $knownUuids = LocationHistory::where('tenant_id', $user->tenant_id)
            ->whereIn('client_uuid', $submittedUuids)
            ->pluck('client_uuid')
            ->flip();

        $parsedTimes = collect();
        foreach ($submitted as $point) {
            try {
                if (isset($point['recorded_at'])) {
                    $parsedTimes->push(CarbonImmutable::parse($point['recorded_at'])->utc());
                }
            } catch (\Throwable) {
                // Per-point validation below returns the canonical rejection.
            }
        }

        $sessions = $this->candidateSessions(
            $user->tenant_id,
            $user->id,
            $parsedTimes
        );

        $accepted = [];
        $duplicates = [];
        $rejected = [];
        $details = [];
        $seenInBatch = [];
        $newestAccepted = null;

        foreach ($submitted as $index => $point) {
            $uuid = $point['client_uuid'] ?? null;

            $reject = function (string $code, ?string $reason = null) use (&$rejected, &$details, $index, $uuid): void {
                if (is_string($uuid) && $uuid !== '') {
                    $rejected[] = $uuid;
                }

                $details[] = [
                    'index' => $index,
                    'client_uuid' => $uuid,
                    'code' => $code,
                    'reason' => $reason ?? $code,
                ];
            };

            if (! is_string($uuid) || ! Str::isUuid($uuid)) {
                $reject('malformed_uuid');
                continue;
            }

            if (isset($seenInBatch[$uuid]) || isset($knownUuids[$uuid])) {
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

            if (! is_numeric($latitude) || ! is_numeric($longitude)
                || (float) $latitude < -90 || (float) $latitude > 90
                || (float) $longitude < -180 || (float) $longitude > 180
                || ((float) $latitude === 0.0 && (float) $longitude === 0.0)) {
                $reject('invalid_coordinates');
                continue;
            }

            if (! is_numeric($accuracy) || (float) $accuracy < 0 || (float) $accuracy > 200) {
                $reject('low_accuracy');
                continue;
            }

            if ($speed !== null && (! is_numeric($speed) || (float) $speed < 0 || (float) $speed > 55)) {
                $reject('invalid_speed');
                continue;
            }

            if ($heading !== null && (! is_numeric($heading) || (float) $heading < 0 || (float) $heading > 360)) {
                $reject('invalid_heading');
                continue;
            }

            if ($battery !== null && (! is_numeric($battery) || (int) $battery < 0 || (int) $battery > 100)) {
                $reject('invalid_battery');
                continue;
            }

            try {
                $recordedAt = CarbonImmutable::parse($point['recorded_at'] ?? null)->utc();
            } catch (\Throwable) {
                $reject('invalid_timestamp');
                continue;
            }

            if ($recordedAt->gt(CarbonImmutable::now('UTC')->addMinutes(5))) {
                $reject('future_timestamp');
                continue;
            }

            $session = $this->sessionContaining($sessions, $recordedAt);
            if (! $session) {
                $reject('no_work_session');
                continue;
            }

            try {
                $row = DB::transaction(function () use (
                    $user,
                    $device,
                    $batch,
                    $point,
                    $uuid,
                    $latitude,
                    $longitude,
                    $accuracy,
                    $recordedAt
                ) {
                    $history = LocationHistory::create([
                        'tenant_id' => $user->tenant_id,
                        'user_id' => $user->id,
                        'salesman_id' => $user->salesman->id,
                        'device_id' => $device->id,
                        'client_uuid' => $uuid,
                        'latitude' => $latitude,
                        'longitude' => $longitude,
                        'horizontal_accuracy' => $accuracy,
                        'altitude' => $point['altitude'] ?? null,
                        'speed' => $point['speed'] ?? null,
                        'heading' => $point['heading'] ?? null,
                        'battery_level' => $point['battery_level'] ?? null,
                        'is_charging' => (bool) ($point['is_charging'] ?? false),
                        'network_status' => $point['network_status'] ?? null,
                        'is_mock_location' => (bool) ($point['is_mock_location'] ?? false),
                        'provider' => $point['provider'] ?? null,
                        'recorded_at' => $recordedAt,
                        'received_at' => now(),
                        'sync_batch_id' => $batch->id,
                        'sequence_number' => $point['sequence_number'] ?? null,
                        'metadata' => null,
                    ]);

                    $current = CurrentLocation::where('tenant_id', $user->tenant_id)
                        ->where('user_id', $user->id)
                        ->lockForUpdate()
                        ->first();

                    if (! $current || $recordedAt->gt($current->recorded_at)) {
                        CurrentLocation::updateOrCreate(
                            [
                                'tenant_id' => $user->tenant_id,
                                'user_id' => $user->id,
                            ],
                            [
                                'salesman_id' => $user->salesman->id,
                                'device_id' => $device->id,
                                'location_history_id' => $history->id,
                                'latitude' => $latitude,
                                'longitude' => $longitude,
                                'horizontal_accuracy' => $accuracy,
                                'altitude' => $point['altitude'] ?? null,
                                'speed' => $point['speed'] ?? null,
                                'heading' => $point['heading'] ?? null,
                                'battery_level' => $point['battery_level'] ?? null,
                                'is_charging' => (bool) ($point['is_charging'] ?? false),
                                'network_status' => $point['network_status'] ?? null,
                                'is_mock_location' => (bool) ($point['is_mock_location'] ?? false),
                                'provider' => $point['provider'] ?? null,
                                'recorded_at' => $recordedAt,
                                'received_at' => now(),
                            ]
                        );
                    }

                    return $history;
                });
            } catch (\Illuminate\Database\QueryException $exception) {
                if (str_contains(strtolower($exception->getMessage()), 'duplicate')) {
                    $duplicates[] = $uuid;
                    continue;
                }

                throw $exception;
            }

            $accepted[] = $uuid;
            if ($newestAccepted === null || $recordedAt->gt($newestAccepted['recorded_at'])) {
                $newestAccepted = [
                    'row' => $row,
                    'recorded_at' => $recordedAt,
                ];
            }
        }

        $batch->update(['processed_at' => now()]);

        $this->refreshRedisLatest($user->tenant_id, $user->id);

        return ApiResponse::success([
            'accepted' => count($accepted),
            'rejected' => count($rejected),
            'duplicates' => count($duplicates),
            'batch_id' => $batch->id,
            'accepted_uuids' => array_values(array_unique($accepted)),
            'duplicate_uuids' => array_values(array_unique($duplicates)),
            'rejected_uuids' => array_values(array_unique($rejected)),
            'rejected_details' => $details,
        ], 201);
    }

    public function current(Request $request)
    {
        $user = $request->user();
        $field = (string) $user->id;
        $key = $this->redisKey($user->tenant_id);

        try {
            $cached = Redis::hget($key, $field);
            if ($cached) {
                $decoded = json_decode($cached, true);
                if (is_array($decoded)) {
                    return ApiResponse::success($decoded);
                }
            }
        } catch (\Throwable $exception) {
            Log::warning('GPS latest-location Redis read failed.', [
                'tenant_id' => $user->tenant_id,
                'user_id' => $user->id,
                'exception' => $exception::class,
            ]);
        }

        return ApiResponse::success(
            CurrentLocation::where('tenant_id', $user->tenant_id)
                ->where('user_id', $user->id)
                ->first()
        );
    }

    public function history(Request $request, TenantClock $clock)
    {
        $user = $request->user()->load('tenant');
        $validated = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
        ]);

        $timezone = $clock->timezone($user->tenant);
        $start = CarbonImmutable::parse($validated['date'].' 00:00:00', $timezone)->utc();
        $end = $start->addDay();

        $rows = LocationHistory::where('tenant_id', $user->tenant_id)
            ->where('user_id', $user->id)
            ->where('recorded_at', '>=', $start)
            ->where('recorded_at', '<', $end)
            ->orderBy('recorded_at')
            ->get();

        $distance = 0.0;
        for ($i = 1; $i < $rows->count(); $i++) {
            if ((float) $rows[$i - 1]->horizontal_accuracy > 50 || (float) $rows[$i]->horizontal_accuracy > 50) {
                continue;
            }

            $distance += $this->distance(
                $rows[$i - 1]->latitude,
                $rows[$i - 1]->longitude,
                $rows[$i]->latitude,
                $rows[$i]->longitude
            );
        }

        return ApiResponse::success([
            'locations' => $rows,
            'summary' => [
                'total_points' => $rows->count(),
                'distance_km' => round($distance, 3),
                'first_point' => $rows->first(),
                'last_point' => $rows->last(),
            ],
        ]);
    }

    public function acknowledge(Request $request)
    {
        $validated = $request->validate([
            'policy_version' => ['required', 'string', 'max:50'],
            'acknowledged_at' => ['required', 'date'],
            'app_version' => ['required', 'string', 'max:50'],
        ]);

        $acknowledgedAt = CarbonImmutable::parse($validated['acknowledged_at'])->utc();
        if ($acknowledgedAt->gt(CarbonImmutable::now('UTC')->addMinutes(5))) {
            return ApiResponse::error(
                'Acknowledgement time is too far in the future.',
                422,
                ['acknowledged_at' => ['The acknowledgement time cannot be more than five minutes in the future.']],
                'VALIDATION_ERROR'
            );
        }

        $user = $request->user();
        $device = $request->attributes->get('device');

        $ack = PrivacyAcknowledgement::firstOrCreate(
            [
                'tenant_id' => $user->tenant_id,
                'user_id' => $user->id,
                'device_id' => $device->id,
                'policy_version' => $validated['policy_version'],
            ],
            [
                'uuid' => (string) Str::uuid(),
                'acknowledged_at' => $acknowledgedAt,
                'app_version' => $validated['app_version'],
                'recorded_at' => now(),
            ]
        );

        return ApiResponse::success([
            'id' => $ack->id,
            'uuid' => $ack->uuid,
            'policy_version' => $ack->policy_version,
            'acknowledged_at' => $ack->acknowledged_at?->toISOString(),
            'recorded_at' => $ack->recorded_at?->toISOString(),
        ], $ack->wasRecentlyCreated ? 201 : 200);
    }

    private function candidateSessions(int $tenantId, int $userId, Collection $times): Collection
    {
        if ($times->isEmpty()) {
            return collect();
        }

        $min = $times->sort()->first();
        $max = $times->sortDesc()->first();

        return WorkSession::where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->where('start_time', '<=', $max)
            ->where(function ($query) use ($min) {
                $query->whereNull('end_time')
                    ->orWhere('end_time', '>=', $min);
            })
            ->orderBy('start_time')
            ->get();
    }

    private function sessionContaining(Collection $sessions, CarbonImmutable $recordedAt): ?WorkSession
    {
        return $sessions->first(function (WorkSession $session) use ($recordedAt): bool {
            $start = CarbonImmutable::parse($session->start_time)->utc();
            $end = $session->end_time
                ? CarbonImmutable::parse($session->end_time)->utc()
                : null;

            return ! $recordedAt->lt($start)
                && ($end === null || ! $recordedAt->gt($end));
        });
    }

    private function refreshRedisLatest(int $tenantId, int $userId): void
    {
        try {
            $current = CurrentLocation::where('tenant_id', $tenantId)
                ->where('user_id', $userId)
                ->first();

            if (! $current) {
                return;
            }

            Redis::hset(
                $this->redisKey($tenantId),
                (string) $userId,
                json_encode($current->toArray(), JSON_THROW_ON_ERROR)
            );
        } catch (\Throwable $exception) {
            Log::warning('GPS latest-location Redis write failed.', [
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'exception' => $exception::class,
            ]);
        }
    }

    private function redisKey(int $tenantId): string
    {
        return 'fs:'.$tenantId.':locations:latest';
    }

    private function distance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthKm = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1))
            * cos(deg2rad($lat2))
            * sin($dLon / 2) ** 2;

        return $earthKm * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
