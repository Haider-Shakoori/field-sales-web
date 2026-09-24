<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\SalesmanAssignment;
use App\Services\CustomerReorderRecommendationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReorderRecommendationController extends Controller
{
    public function __invoke(
        Request $request,
        Customer $customer,
        CustomerReorderRecommendationService $recommendations,
    ): JsonResponse {
        abort_unless(
            $request->user()->hasPermission('orders:view')
                && $request->user()->hasPermission('customers:view'),
            403,
        );

        $user = $request->user()->load(['salesman', 'tenant']);
        abort_unless($user->salesman?->is_active, 403);
        $this->ensureCustomerVisible($request, $customer);

        return ApiResponse::success([
            'customer_id' => $customer->uuid,
            'customer_name' => $customer->name,
            'generated_at' => now()->toISOString(),
            'lookback_days' => 365,
            'recommendations' => $recommendations->recommend(
                $customer->loadMissing('tenant'),
                $user->salesman,
            ),
        ]);
    }

    private function ensureCustomerVisible(Request $request, Customer $customer): void
    {
        if (! $request->user()->hasAnyRole(['salesman'])) {
            return;
        }

        $user = $request->user()->loadMissing(['salesman', 'tenant']);
        $date = now($user->tenant->timezone)->toDateString();
        $assignment = SalesmanAssignment::query()
            ->where('salesman_id', $user->salesman->id)
            ->current($date)
            ->latest('effective_from')
            ->first();

        $visible = (int) $customer->created_by === (int) $user->id;

        if (! $visible && $assignment?->route_id) {
            $visible = $customer->routeMemberships()->where('route_id', $assignment->route_id)->exists();
        } elseif (! $visible && $assignment?->territory_id) {
            $visible = (int) $customer->territory_id === (int) $assignment->territory_id;
        } elseif (! $visible && $assignment?->branch_id) {
            $visible = (int) $customer->branch_id === (int) $assignment->branch_id;
        }

        abort_unless($visible, 404);
    }
}
