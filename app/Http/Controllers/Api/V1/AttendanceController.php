<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\EndWorkSessionRequest;
use App\Http\Requests\Api\V1\StartWorkSessionRequest;
use App\Http\Resources\WorkSessionResource;
use App\Models\Device;
use App\Models\WorkSession;
use App\Services\AttendanceService;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Attendance / work-session endpoints (API_CONTRACT.md §8.5).
 *
 * Identity is derived server-side from the authenticated, device-bound request;
 * `started_at` / `ended_at` in the API map to `start_time` / `end_time` columns.
 */
class AttendanceController extends Controller
{
    public function start(StartWorkSessionRequest $request, AttendanceService $service): JsonResponse
    {
        [$session, $created] = $service->start($request->user(), $this->deviceFrom($request), $request->validated());

        return ApiResponse::success(new WorkSessionResource($session), status: $created ? 201 : 200);
    }

    public function end(EndWorkSessionRequest $request, AttendanceService $service): JsonResponse
    {
        [$session] = $service->end($request->user(), $request->validated());

        return ApiResponse::success(new WorkSessionResource($session));
    }

    public function today(Request $request, AttendanceService $service): JsonResponse
    {
        $session = $service->today($request->user());

        return ApiResponse::success($session !== null ? new WorkSessionResource($session) : null);
    }

    public function history(Request $request): JsonResponse
    {
        $query = WorkSession::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('date')
            ->orderByDesc('start_time');

        if ($date = $request->string('date')->toString()) {
            $query->where('date', $date);
        }

        $perPage = min(max($request->integer('per_page', 25), 1), 100);
        $sessions = $query->paginate($perPage);

        return ApiResponse::success(
            WorkSessionResource::collection($sessions),
            [
                'page' => $sessions->currentPage(),
                'per_page' => $sessions->perPage(),
                'total' => $sessions->total(),
                'last_page' => $sessions->lastPage(),
            ],
        );
    }

    private function deviceFrom(Request $request): Device
    {
        $device = $request->attributes->get('device');

        abort_if(! $device instanceof Device, 403, 'Device identification is required.');

        return $device;
    }
}
