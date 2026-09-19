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
        $canTrack = $user->hasPermission('tracking:view');
        $locations = $dashboard->liveLocations($user);

        return view('admin.dashboard.index', [
            'summary' => $dashboard->summary($user, $locations),
            'analytics' => $dashboard->analytics($user),
            'recentActivity' => $dashboard->recentActivity($user),
            'initialLocations' => $canTrack ? $locations : [],
            'variant' => $dashboard->dashboardVariant($user),
            'visibility' => [
                'tracking' => $canTrack,
                'orders' => $user->hasPermission('orders:view'),
                'collections' => $user->hasPermission('collections:view'),
                'expenses' => $user->hasPermission('expenses:view'),
                'visits' => $user->hasPermission('visits:view'),
            ],
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
