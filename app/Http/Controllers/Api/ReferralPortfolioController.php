<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Collection;
use App\Models\CustomerVisit;
use App\Models\Order;
use App\Services\CustomerBalanceService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReferralPortfolioController extends Controller
{
    public function __invoke(
        Request $request,
        CustomerBalanceService $balances,
    ): JsonResponse {
        $actor = $request->user()->loadMissing(['salesman', 'tenant']);

        abort_unless(
            $actor->hasAnyRole(['salesman']) && $actor->salesman,
            403,
        );

        $salesman = $actor->salesman;
        $perPage = min(100, max(10, $request->integer('per_page', 30)));

        $customers = $salesman->referredCustomers()
            ->with(['territory', 'assignedSalesman'])
            ->withCount(['orders', 'collections', 'visits'])
            ->orderBy('name')
            ->paginate($perPage);

        $balanceRows = collect(
            $balances->forCustomers($customers->getCollection())
        )->keyBy('customer_id');

        $customerIds = $salesman->referredCustomers()->select('customers.id');

        $summary = [
            'referred_customers' => $salesman->referredCustomers()->count(),
            'active_customers' => $salesman->referredCustomers()->active()->count(),
            'orders' => Order::query()
                ->whereIn('customer_id', clone $customerIds)
                ->count(),
            'collections' => Collection::query()
                ->whereIn('customer_id', clone $customerIds)
                ->count(),
            'visits' => CustomerVisit::query()
                ->whereIn('customer_id', clone $customerIds)
                ->count(),
        ];

        return ApiResponse::success([
            'salesman' => [
                'id' => $salesman->uuid,
                'employee_code' => $salesman->employee_code,
                'name' => $salesman->full_name,
            ],
            'summary' => $summary,
            'customers' => $customers->getCollection()
                ->map(function ($customer) use ($balanceRows, $salesman): array {
                    $balance = $balanceRows->get($customer->uuid);

                    return [
                        'id' => $customer->uuid,
                        'code' => $customer->code,
                        'name' => $customer->name,
                        'territory' => $customer->territory?->name,
                        'is_active' => $customer->is_active,
                        'assigned_to_me' => (int) $customer->assigned_salesman_id === (int) $salesman->id,
                        'assigned_salesman' => $customer->assignedSalesman?->full_name,
                        'orders' => (int) $customer->orders_count,
                        'collections' => (int) $customer->collections_count,
                        'visits' => (int) $customer->visits_count,
                        'balances' => $balance['balances'] ?? [],
                    ];
                })
                ->values()
                ->all(),
        ], 200, [
            'pagination' => [
                'current_page' => $customers->currentPage(),
                'last_page' => $customers->lastPage(),
                'per_page' => $customers->perPage(),
                'total' => $customers->total(),
            ],
        ]);
    }
}
