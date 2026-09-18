<?php

namespace App\Services;

use App\Http\Resources\CurrentLocationResource;
use App\Models\CurrentLocation;
use App\Models\Device;
use App\Models\LocationHistory;
use App\Models\LocationSyncBatch;
use App\Models\User;
use App\Models\WorkSession;
use App\Support\Tenancy\TenantContext;
use App\Support\Tracking\LatestLocationCache;
use App\Support\Tracking\TenantClock;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * GPS batch ingestion (Batch 7, GPS Tracking Core).
 *
 * Identity (tenant, user, salesman, device) is always derived server-side. Each
 * point is validated and deduplicated independently so one bad point never
 * rejects a whole batch. `location_history` is append-only; `current_locations`
 * only advances when an accepted point is newer than the stored state, which
 * keeps out-of-order offline uploads from regressing the latest location.
 *
 * Redis is written after the database transaction commits and never affects
 * database truth.
 */
class GpsIngestionService
{
    public function __construct(private readonly LatestLocationCache $cache) {}

    /**
     * @param  array<int, mixed>  $locations
     * @return array{
     *     accepted: int,
     *     rejected: int,
     *     duplicates: int,
     *     batch_id: int,
     *     accepted_uuids: list<string>,
     *     duplicate_uuids: list<string>,
     *     rejected_uuids: list<string>,
     *     rejected_details: list<array<string, mixed>>
     * }
     */
    public function ingest(User $user, Device $device, array $locations, string $batchUuid): array
    {
        $tenantId = TenantContext::currentId();
        abort_if($tenantId === null, 403, 'A tenant context is required to upload locations.');

        $salesman = $user->salesmanProfile;
        abort_if($salesman === null, 403, 'Only salesmen with a field profile can upload locations.');

        $timezone = TenantClock::timezoneFor($user);
        $existingUuids = $this->existingClientUuids($locations);

        $result = DB::transaction(function () use ($user, $device, $tenantId, $salesman, $locations, $batchUuid, $timezone, $existingUuids): array {
            $receivedAt = CarbonImmutable::now('UTC');
            $batch = $this->resolveBatch($tenantId, $user->id, $device->id, $batchUuid, count($locations), $receivedAt);

            $seen = array_fill_keys($existingUuids, true);
            $acceptedRows = [];
            $acceptedUuids = [];
            $duplicateUuids = [];
            $rejectedUuids = [];
            $rejectedDetails = [];
            $duplicates = 0;
            $newest = null;
            $sessionDates = [];

            foreach ($locations as $index => $point) {
                if (! is_array($point)) {
                    $rejectedDetails[] = ['index' => $index, 'code' => 'malformed_point', 'reason' => 'malformed_point'];

                    continue;
                }

                $clientUuid = $point['client_uuid'] ?? null;

                if (! is_string($clientUuid) || ! Str::isUuid($clientUuid)) {
                    $rejectedDetails[] = [
                        'index' => $index,
                        'client_uuid' => is_string($clientUuid) ? $clientUuid : null,
                        'code' => 'invalid_client_uuid',
                        'reason' => 'invalid_client_uuid',
                    ];

                    continue;
                }

                if (isset($seen[$clientUuid])) {
                    $duplicates++;
                    $duplicateUuids[] = $clientUuid;

                    continue;
                }

                [$validated, $reason] = $this->validatePoint($point, $receivedAt, $clientUuid);

                if ($validated === null) {
                    $rejectedDetails[] = ['index' => $index, 'client_uuid' => $clientUuid, 'code' => $reason, 'reason' => $reason];
                    $rejectedUuids[] = $clientUuid;

                    continue;
                }

                $localDate = $validated['recorded_at']->setTimezone($timezone)->toDateString();

                if (! array_key_exists($localDate, $sessionDates)) {
                    $sessionDates[$localDate] = WorkSession::query()
                        ->where('user_id', $user->id)
                        ->whereDate('date', $localDate)
                        ->exists();
                }

                if (! $sessionDates[$localDate]) {
                    $rejectedDetails[] = ['index' => $index, 'client_uuid' => $clientUuid, 'code' => 'no_work_session', 'reason' => 'no_work_session'];
                    $rejectedUuids[] = $clientUuid;

                    continue;
                }

                $seen[$clientUuid] = true;
                $acceptedUuids[] = $clientUuid;

                $acceptedRows[] = $this->historyRow(
                    $validated,
                    $tenantId,
                    $user->id,
                    $salesman->id,
                    $device->id,
                    $batch->id,
                    $receivedAt,
                );

                if ($newest === null || $validated['recorded_at']->greaterThan($newest['recorded_at'])) {
                    $newest = $validated;
                }
            }

            if ($acceptedRows !== []) {
                LocationHistory::insert($acceptedRows);
            }

            $current = $newest !== null
                ? $this->updateCurrentLocation($tenantId, $user->id, $salesman->id, $device->id, $newest, $receivedAt)
                : null;

            $batch->update([
                'point_count' => count($locations),
                'processed_at' => CarbonImmutable::now('UTC'),
            ]);

            return [
                'accepted' => count($acceptedRows),
                'rejected' => count($rejectedDetails),
                'duplicates' => $duplicates,
                'batch_id' => $batch->id,
                'accepted_uuids' => $acceptedUuids,
                'duplicate_uuids' => $duplicateUuids,
                'rejected_uuids' => $rejectedUuids,
                'rejected_details' => $rejectedDetails,
                'cache_payload' => $current !== null
                    ? (new CurrentLocationResource($current))->resolve(request())
                    : null,
            ];
        });

        if ($result['cache_payload'] !== null) {
            $this->cache->put($tenantId, $user->id, $result['cache_payload']);
        }

        unset($result['cache_payload']);

        return $result;
    }

    /**
     * @param  array<int, mixed>  $locations
     * @return list<string>
     */
    private function existingClientUuids(array $locations): array
    {
        $uuids = collect($locations)
            ->filter(fn ($point) => is_array($point) && is_string($point['client_uuid'] ?? null))
            ->pluck('client_uuid')
            ->unique()
            ->values()
            ->all();

        if ($uuids === []) {
            return [];
        }

        return LocationHistory::query()
            ->whereIn('client_uuid', $uuids)
            ->pluck('client_uuid')
            ->all();
    }

    private function resolveBatch(int $tenantId, int $userId, int $deviceId, string $batchUuid, int $pointCount, CarbonImmutable $receivedAt): LocationSyncBatch
    {
        $batch = LocationSyncBatch::query()->where('batch_uuid', $batchUuid)->first();

        if ($batch !== null) {
            return $batch;
        }

        return LocationSyncBatch::create([
            'tenant_id' => $tenantId,
            'device_id' => $deviceId,
            'user_id' => $userId,
            'batch_uuid' => $batchUuid,
            'point_count' => $pointCount,
            'received_at' => $receivedAt,
        ]);
    }

    /**
     * Validate one GPS point against the canonical quality rules.
     *
     * @param  array<string, mixed>  $point
     * @return array{0: array<string, mixed>|null, 1: string|null}
     */
    private function validatePoint(array $point, CarbonImmutable $receivedAt, string $clientUuid): array
    {
        $maxAccuracy = (float) config('tenancy.tracking.max_accuracy_meters', 200);
        $maxSpeed = (float) config('tenancy.tracking.max_speed_mps', 55);
        $futureTolerance = (int) config('tenancy.tracking.future_tolerance_minutes', 5);

        $latitude = $point['latitude'] ?? null;

        if (! is_numeric($latitude) || (float) $latitude < -90 || (float) $latitude > 90) {
            return [null, 'invalid_latitude'];
        }

        $longitude = $point['longitude'] ?? null;

        if (! is_numeric($longitude) || (float) $longitude < -180 || (float) $longitude > 180) {
            return [null, 'invalid_longitude'];
        }

        if ((float) $latitude === 0.0 && (float) $longitude === 0.0) {
            return [null, 'empty_coordinates'];
        }

        $recordedRaw = $point['recorded_at'] ?? null;

        if (! is_string($recordedRaw) || $recordedRaw === '') {
            return [null, 'invalid_recorded_at'];
        }

        try {
            $recordedAt = CarbonImmutable::parse($recordedRaw)->utc();
        } catch (Throwable) {
            return [null, 'invalid_recorded_at'];
        }

        if ($recordedAt->greaterThan($receivedAt->addMinutes($futureTolerance))) {
            return [null, 'future_recorded_at'];
        }

        $accuracy = $point['accuracy'] ?? null;

        if ($accuracy !== null && (! is_numeric($accuracy) || (float) $accuracy < 0 || (float) $accuracy > $maxAccuracy)) {
            return [null, 'low_accuracy'];
        }

        $speed = $point['speed'] ?? null;

        if ($speed !== null && (! is_numeric($speed) || (float) $speed < 0 || (float) $speed > $maxSpeed)) {
            return [null, 'impossible_speed'];
        }

        $heading = $point['heading'] ?? null;

        if ($heading !== null && (! is_numeric($heading) || (float) $heading < 0 || (float) $heading > 360)) {
            return [null, 'invalid_heading'];
        }

        $battery = $point['battery_level'] ?? null;

        if ($battery !== null && (! is_numeric($battery) || (int) $battery < 0 || (int) $battery > 100)) {
            return [null, 'invalid_battery_level'];
        }

        $sequence = $point['sequence_number'] ?? null;

        if ($sequence !== null && (! is_numeric($sequence) || (int) $sequence < 0)) {
            return [null, 'invalid_sequence_number'];
        }

        $metadata = $point['metadata'] ?? null;

        if ($metadata !== null && ! is_array($metadata)) {
            return [null, 'invalid_metadata'];
        }

        $network = $point['network_status'] ?? null;

        if ($network !== null && (! is_string($network) || mb_strlen($network) > 20)) {
            return [null, 'invalid_network_status'];
        }

        $provider = $point['provider'] ?? null;

        if ($provider !== null && (! is_string($provider) || mb_strlen($provider) > 50)) {
            return [null, 'invalid_provider'];
        }

        return [[
            'client_uuid' => $clientUuid,
            'latitude' => round((float) $latitude, 7),
            'longitude' => round((float) $longitude, 7),
            'horizontal_accuracy' => $accuracy !== null ? round((float) $accuracy, 2) : null,
            'altitude' => is_numeric($point['altitude'] ?? null) ? round((float) $point['altitude'], 2) : null,
            'speed' => $speed !== null ? round((float) $speed, 2) : null,
            'heading' => $heading !== null ? round((float) $heading, 2) : null,
            'battery_level' => $battery !== null ? (int) $battery : null,
            'is_charging' => (bool) ($point['is_charging'] ?? false),
            'network_status' => $network,
            'is_mock_location' => (bool) ($point['is_mock_location'] ?? false),
            'provider' => $provider,
            'recorded_at' => $recordedAt,
            'metadata' => $metadata,
            'sequence_number' => $sequence !== null ? (int) $sequence : null,
        ], null];
    }

    /**
     * @param  array<string, mixed>  $point
     * @return array<string, mixed>
     */
    private function historyRow(array $point, int $tenantId, int $userId, int $salesmanId, int $deviceId, int $batchId, CarbonImmutable $receivedAt): array
    {
        return [
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'salesman_id' => $salesmanId,
            'device_id' => $deviceId,
            'client_uuid' => $point['client_uuid'],
            'latitude' => $point['latitude'],
            'longitude' => $point['longitude'],
            'horizontal_accuracy' => $point['horizontal_accuracy'],
            'altitude' => $point['altitude'],
            'speed' => $point['speed'],
            'heading' => $point['heading'],
            'battery_level' => $point['battery_level'],
            'is_charging' => $point['is_charging'],
            'network_status' => $point['network_status'],
            'is_mock_location' => $point['is_mock_location'],
            'provider' => $point['provider'],
            'recorded_at' => $point['recorded_at']->format('Y-m-d H:i:s'),
            'received_at' => $receivedAt->format('Y-m-d H:i:s'),
            'sync_batch_id' => $batchId,
            'sequence_number' => $point['sequence_number'],
            'metadata' => $point['metadata'] !== null ? json_encode($point['metadata']) : null,
            'created_at' => $receivedAt->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Advance the materialized latest location only for strictly newer points.
     * Returns the updated model, or null when the incoming point is older.
     *
     * @param  array<string, mixed>  $point
     */
    private function updateCurrentLocation(int $tenantId, int $userId, int $salesmanId, int $deviceId, array $point, CarbonImmutable $receivedAt): ?CurrentLocation
    {
        $attributes = [
            'salesman_id' => $salesmanId,
            'device_id' => $deviceId,
            'latitude' => $point['latitude'],
            'longitude' => $point['longitude'],
            'horizontal_accuracy' => $point['horizontal_accuracy'],
            'altitude' => $point['altitude'],
            'speed' => $point['speed'],
            'heading' => $point['heading'],
            'battery_level' => $point['battery_level'],
            'is_charging' => $point['is_charging'],
            'network_status' => $point['network_status'],
            'is_mock_location' => $point['is_mock_location'],
            'provider' => $point['provider'],
            'recorded_at' => $point['recorded_at'],
            'received_at' => $receivedAt,
        ];

        $existing = CurrentLocation::query()
            ->where('user_id', $userId)
            ->lockForUpdate()
            ->first();

        if ($existing === null) {
            return CurrentLocation::create($attributes + ['tenant_id' => $tenantId, 'user_id' => $userId]);
        }

        if ($point['recorded_at']->greaterThan($existing->recorded_at)) {
            $existing->update($attributes);

            return $existing;
        }

        return null;
    }
}
