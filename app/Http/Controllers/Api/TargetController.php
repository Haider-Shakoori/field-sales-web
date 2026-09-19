<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SalesTarget;
use App\Services\TargetProgressService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TargetController extends Controller
{
    public function current(Request $request, TargetProgressService $progress): JsonResponse
    {
        abort_unless($request->user()->hasPermission('targets:view'), 403);

        $user = $request->user()->load(['salesman', 'tenant']);
        abort_unless($user->salesman?->is_active, 403);

        $today = now($user->tenant->timezone)->toDateString();

        $targets = SalesTarget::with(['salesman.user', 'tenant'])
            ->where('salesman_id', $user->salesman->id)
            ->whereDate('period_start', '<=', $today)
            ->whereDate('period_end', '>=', $today)
            ->orderBy('target_type')
            ->get()
            ->map(fn (SalesTarget $target) => $progress->payload($target))
            ->values()
            ->all();

        return ApiResponse::success($targets);
    }

    public function history(Request $request, TargetProgressService $progress): JsonResponse
    {
        abort_unless($request->user()->hasPermission('targets:view'), 403);

        $user = $request->user()->load('salesman');
        abort_unless($user->salesman?->is_active, 403);

        $validated = $request->validate([
            'per_page' => ['nullable', 'integer', 'between:1,100'],
        ]);

        $page = SalesTarget::with(['salesman.user', 'tenant'])
            ->where('salesman_id', $user->salesman->id)
            ->orderByDesc('period_start')
            ->paginate((int) ($validated['per_page'] ?? 30));

        return ApiResponse::success(
            collect($page->items())
                ->map(fn (SalesTarget $target) => $progress->payload($target))
                ->values()
                ->all(),
            200,
            [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        );
    }
}
