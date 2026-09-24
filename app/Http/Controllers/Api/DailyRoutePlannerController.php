<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DailyRoutePlannerService;
use App\Support\ApiResponse;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DailyRoutePlannerController extends Controller
{
    public function today(
        Request $request,
        DailyRoutePlannerService $planner,
    ): JsonResponse {
        $user = $request->user()->load(['tenant', 'salesman']);

        abort_unless($user->salesman?->is_active, 403);
        abort_unless($user->hasPermission('customers:view'), 403);

        $validated = $request->validate([
            'latitude' => ['nullable', 'required_with:longitude', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'required_with:latitude', 'numeric', 'between:-180,180'],
            'accuracy' => ['nullable', 'numeric', 'between:0,200'],
        ]);

        $date = CarbonImmutable::now(
            $user->tenant?->timezone ?: config('app.timezone', 'UTC')
        )->startOfDay();

        $startLocation = null;

        if (isset($validated['latitude'], $validated['longitude'])) {
            $latitude = (float) $validated['latitude'];
            $longitude = (float) $validated['longitude'];

            if (! ($latitude === 0.0 && $longitude === 0.0)) {
                $startLocation = [
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'accuracy' => isset($validated['accuracy'])
                        ? (float) $validated['accuracy']
                        : null,
                    'source' => 'device_current',
                ];
            }
        }

        return ApiResponse::success(
            $planner->planFor($user->salesman, $date, $startLocation)
        );
    }
}
