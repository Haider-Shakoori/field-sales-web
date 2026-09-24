<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ReorderRecommendationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReorderRecommendationController extends Controller
{
    public function __invoke(Request $request, ReorderRecommendationService $service): JsonResponse
    {
        abort_unless($request->user()->hasPermission('orders:view'), 403);
        $user = $request->user()->load('salesman');
        abort_unless($user->salesman?->is_active, 403);

        return ApiResponse::success($service->forTenant($user->salesman)->take(100)->values()->all());
    }
}