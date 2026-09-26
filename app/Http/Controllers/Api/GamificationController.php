<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\GamificationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GamificationController extends Controller
{
    public function show(Request $request, GamificationService $gamification): JsonResponse
    {
        $user = $request->user()->loadMissing(['tenant', 'salesman']);
        $enabled = $gamification->enabled($user->tenant);

        if (! $enabled) {
            return ApiResponse::success(['enabled' => false]);
        }

        $gamification->sync($user->tenant);
        $leaderboard = $gamification->leaderboard($user->tenant);
        $mine = $user->salesman
            ? $leaderboard->first(fn (array $row) => $row['salesman']->id === $user->salesman->id)
            : null;

        return ApiResponse::success([
            'enabled' => true,
            'me' => $mine ? $this->row($mine) : null,
            'leaderboard' => $leaderboard->take(20)->map(fn (array $row) => $this->row($row))->values()->all(),
            'rules' => GamificationService::POINTS,
        ]);
    }

    private function row(array $row): array
    {
        return [
            'rank' => $row['rank'],
            'salesman_id' => $row['salesman']->uuid,
            'salesman_name' => $row['salesman']->full_name,
            'employee_code' => $row['salesman']->employee_code,
            'points' => $row['points'],
            'events' => $row['events'],
            'active_days' => $row['active_days'],
            'level' => $row['level'],
            'achievements' => $row['achievements'],
        ];
    }
}
