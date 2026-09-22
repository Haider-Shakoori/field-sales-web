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

        $date = CarbonImmutable::now(
            $user->tenant?->timezone ?: config('app.timezone', 'UTC')
        )->startOfDay();

        return ApiResponse::success(
            $planner->planFor($user->salesman, $date)
        );
    }
}
