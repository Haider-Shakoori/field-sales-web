<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request, DashboardService $dashboard): View
    {
        $user = $request->user()->loadMissing('tenant');

        return view('admin.dashboard.index', [
            'summary' => $dashboard->summary($user),
            'recentActivity' => $dashboard->recentActivity($user),
            'canTrack' => $user->hasPermission('tracking:view'),
        ]);
    }

    public function liveLocations(
        Request $request,
        DashboardService $dashboard,
    ): JsonResponse {
        $user = $request->user()->loadMissing('tenant');

        return response()
            ->json([
                'data' => $dashboard->liveLocations($user),
                'generated_at' => now()->toISOString(),
                'refresh_after_seconds' => 30,
                'freshness' => [
                    'live_max_seconds' => 300,
                    'stale_max_seconds' => 1800,
                ],
            ])
            ->header('Cache-Control', 'no-store, private');
    }
}
