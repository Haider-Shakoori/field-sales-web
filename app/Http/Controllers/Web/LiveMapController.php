<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LiveMapController extends Controller
{
    public function index(Request $request, DashboardService $dashboard): View
    {
        $user = $request->user()->loadMissing('tenant');

        return view('admin.live-map.index', [
            'initialLocations' => $dashboard->liveLocations($user, null, true),
        ]);
    }
}
