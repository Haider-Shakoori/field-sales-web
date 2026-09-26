<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\GamificationService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GamificationController extends Controller
{
    public function __invoke(Request $request, GamificationService $gamification): View
    {
        $user = $request->user()->loadMissing('tenant');
        $enabled = $gamification->enabled($user->tenant);

        if ($enabled) {
            $gamification->sync($user->tenant);
        }

        return view('admin.gamification.index', [
            'enabled' => $enabled,
            'leaderboard' => $enabled
                ? $gamification->leaderboard($user->tenant)
                : collect(),
            'rules' => GamificationService::POINTS,
        ]);
    }
}
