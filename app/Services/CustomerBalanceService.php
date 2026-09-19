<?php

namespace App\Services;

use App\Models\Collection;
use App\Models\Customer;
use App\Models\Order;
use Illuminate\Support\Collection as SupportCollection;

class CustomerBalanceService
{
    public function forCustomer(Customer $customer): array
    {
        $orderTotals = Order::query()
            ->where('customer_id', $customer->id)
            ->where('payment_type', 'credit')
            ->where('status', 'approved')
            ->selectRaw('currency, SUM(grand_total) as total')
            ->groupBy('currency')
            ->pluck('total', 'currency');

        $verifiedTotals = Collection::query()
            ->where('customer_id', $customer->id)
            ->where('status', 'verified')
            ->selectRaw('currency, SUM(amount) as total')
            ->groupBy('currency')
            ->pluck('total', 'currency');

        $pendingTotals = Collection::query()
            ->where('customer_id', $customer->id)
            ->where('status', 'pending')
            ->selectRaw('currency, SUM(amount) as total')
            ->groupBy('currency')
            ->pluck('total', 'currency');

        $currencies = collect()
            ->merge($orderTotals->keys())
            ->merge($verifiedTotals->keys())
            ->merge($pendingTotals->keys())
            ->unique()
            ->sort()
            ->values();

        return $currencies->map(function (string $currency) use (
            $orderTotals,
            $verifiedTotals,
            $pendingTotals,
        ): array {
            $receivable = round((float) ($orderTotals[$currency] ?? 0), 4);
            $verified = round((float) ($verifiedTotals[$currency] ?? 0), 4);
            $pending = round((float) ($pendingTotals[$currency] ?? 0), 4);
            $outstanding = round(max(0, $receivable - $verified), 4);
            $available = round(max(0, $outstanding - $pending), 4);

            return [
                'currency' => $currency,
                'receivable_total' => $receivable,
                'verified_collections' => $verified,
                'pending_collections' => $pending,
                'outstanding_balance' => $outstanding,
                'available_to_collect' => $available,
            ];
        })->all();
    }

    public function snapshot(Customer $customer, string $currency): array
    {
        $currency = strtoupper($currency);
        $row = collect($this->forCustomer($customer))
            ->firstWhere('currency', $currency);

        return $row ?? [
            'currency' => $currency,
            'receivable_total' => 0.0,
            'verified_collections' => 0.0,
            'pending_collections' => 0.0,
            'outstanding_balance' => 0.0,
            'available_to_collect' => 0.0,
        ];
    }

    public function outstanding(Customer $customer, string $currency): float
    {
        return round(
            (float) $this->snapshot($customer, $currency)['outstanding_balance'],
            4,
        );
    }

    public function availableToCollect(Customer $customer, string $currency): float
    {
        return round(
            (float) $this->snapshot($customer, $currency)['available_to_collect'],
            4,
        );
    }

    public function forCustomers(SupportCollection $customers): array
    {
        return $customers
            ->map(fn (Customer $customer) => [
                'customer_id' => $customer->uuid,
                'customer_name' => $customer->name,
                'balances' => $this->forCustomer($customer),
            ])
            ->values()
            ->all();
    }
}
