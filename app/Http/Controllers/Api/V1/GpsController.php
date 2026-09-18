<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreLocationsRequest;
use App\Http\Resources\CurrentLocationResource;
use App\Http\Resources\LocationHistoryResource;
use App\Models\CurrentLocation;
use App\Models\Device;
use App\Models\LocationHistory;
use App\Models\User;
use App\Services\GpsIngestionService;
use App\Support\Http\ApiResponse;
use App\Support\Tenancy\TenantContext;
use App\Support\Tracking\LatestLocationCache;
use App\Support\Tracking\TenantClock;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * GPS endpoints (API_CONTRACT.md §8.6).
 *
 * Upload identity is always the authenticated device-bound user; read endpoints
 * default to the caller and only allow documented scoped access for users with
 * the tracking:view permission.
 */
class GpsController extends Controller
{
    public function store(StoreLocationsRequest $request, GpsIngestionService $service): JsonResponse
    {
        $device = $request->attributes->get('device');

        abort_if(! $device instanceof Device, 403, 'Device identification is required.');

        $validated = $request->validated();

        $result = $service->ingest(
            $request->user(),
            $device,
            $validated['locations'],
            $validated['batch_uuid'],
        );

        return ApiResponse::success($result, status: 201);
    }

    public function current(Request $request, LatestLocationCache $cache): JsonResponse
    {
        $user = $request->user();
        $tenantId = TenantContext::currentId();

        abort_if($tenantId === null, 403, 'A tenant context is required.');

        $cached = $cache->get($tenantId, $user->id);

        if ($cached !== null) {
            return ApiResponse::success($cached);
        }

        $current = CurrentLocation::query()->where('user_id', $user->id)->first();

        return ApiResponse::success($current !== null ? new CurrentLocationResource($current) : null);
    }

    public function history(Request $request): JsonResponse
    {
        $user = $request->user();
        $tenantId = TenantContext::currentId();

        abort_if($tenantId === null, 403, 'A tenant context is required.');

        $targetUserId = $user->id;

        if ($request->filled('user_id') && (int) $request->integer('user_id') !== $user->id) {
            abort_unless(
                $user->hasPermission('tracking:view'),
                403,
                'You are not allowed to view other users\' locations.',
            );

            $targetUserId = User::query()->findOrFail($request->integer('user_id'))->id;
        }

        $localDate = $this->resolveLocalDate($request, $user);

        [$start, $end] = TenantClock::utcRangeForLocalDate($user, $localDate);

        $query = LocationHistory::query()
            ->where('user_id', $targetUserId)
            ->where('recorded_at', '>=', $start)
            ->where('recorded_at', '<', $end);

        $perPage = min(max($request->integer('per_page', 500), 1), 2000);
        $points = (clone $query)->orderBy('recorded_at')->paginate($perPage);

        $summaryPoints = (clone $query)
            ->orderBy('recorded_at')
            ->get(['latitude', 'longitude', 'horizontal_accuracy', 'recorded_at']);

        $summary = [
            'date' => $localDate,
            'total_points' => $summaryPoints->count(),
            'distance_km' => round($this->distanceKm($summaryPoints), 2),
            'first_point_at' => $summaryPoints->first()?->recorded_at?->toIso8601String(),
            'last_point_at' => $summaryPoints->last()?->recorded_at?->toIso8601String(),
        ];

        return ApiResponse::success(
            [
                'locations' => LocationHistoryResource::collection($points),
                'summary' => $summary,
            ],
            [
                'page' => $points->currentPage(),
                'per_page' => $points->perPage(),
                'total' => $points->total(),
                'last_page' => $points->lastPage(),
            ],
        );
    }

    private function resolveLocalDate(Request $request, User $user): string
    {
        $date = $request->string('date')->toString();

        if ($date === '') {
            return TenantClock::dateFor($user);
        }

        try {
            return CarbonImmutable::parse($date)->toDateString();
        } catch (Throwable) {
            throw ValidationException::withMessages([
                'date' => 'The date field must be a valid date.',
            ]);
        }
    }

    private function distanceKm(Collection $points): float
    {
        $distance = 0.0;
        $previous = null;

        foreach ($points as $point) {
            if ($previous !== null) {
                $accuracyOk = ($previous->horizontal_accuracy === null || (float) $previous->horizontal_accuracy <= 50)
                    && ($point->horizontal_accuracy === null || (float) $point->horizontal_accuracy <= 50);

                if ($accuracyOk) {
                    $distance += $this->haversineMeters(
                        (float) $previous->latitude,
                        (float) $previous->longitude,
                        (float) $point->latitude,
                        (float) $point->longitude,
                    );
                }
            }

            $previous = $point;
        }

        return $distance / 1000;
    }

    private function haversineMeters(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371000.0;
        $latDelta = deg2rad($lat2 - $lat1);
        $lonDelta = deg2rad($lon2 - $lon1);

        $a = sin($latDelta / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($lonDelta / 2) ** 2;

        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
