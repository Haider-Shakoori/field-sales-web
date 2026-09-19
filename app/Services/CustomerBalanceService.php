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
        return $this->forCustomers(collect([$customer]))[0]['balances'] ?? [];
    }

    public function outstanding(Customer $customer, string $currency): float
    {
        return $this->snapshot($customer, $currency)['outstanding_balance'];
    }

    public function snapshot(Customer $customer, string $currency): array
    {
        $currency = strtoupper($currency);
        $row = collect($this->forCustomer($customer))
            ->firstWhere('currency', $currency);

        $receivable = round((float) ($row['receivable_total'] ?? 0), 4);
        $verified = round((float) ($row['verified_collections'] ?? 0), 4);
        $pending = round((float) ($row['pending_collections'] ?? 0), 4);
        $outstanding = round(max(0, $receivable - $verified), 4);

        return [
            'currency' => $currency,
            'receivable_total' => $receivable,
            'verified_collections' => $verified,
            'pending_collections' => $pending,
            'outstanding_balance' => $outstanding,
            'available_to_collect' => round(max(0, $outstanding - $pending), 4),
        ];
    }

    public function forCustomers(SupportCollection $customers): array
    {
        if ($customers->isEmpty()) {
            return [];
        }

        $customerIds = $customers->pluck('id')->all();

        $orderRows = Order::query()
            ->whereIn('customer_id', $customerIds)
            ->where('payment_type', 'credit')
            ->where('status', 'approved')
            ->selectRaw('customer_id, currency, SUM(grand_total) as total')
            ->groupBy('customer_id', 'currency')
            ->get();

        $verifiedRows = Collection::query()
            ->whereIn('customer_id', $customerIds)
            ->where('status', 'verified')
            ->selectRaw('customer_id, currency, SUM(amount) as total')
            ->groupBy('customer_id', 'currency')
            ->get();

        $pendingRows = Collection::query()
            ->whereIn('customer_id', $customerIds)
            ->where('status', 'pending')
            ->selectRaw('customer_id, currency, SUM(amount) as total')
            ->groupBy('customer_id', 'currency')
            ->get();

        $orders = $this->indexTotals($orderRows);
        $verified = $this->indexTotals($verifiedRows);
        $pending = $this->indexTotals($pendingRows);

        return $customers->map(function (Customer $customer) use (
            $orders,
            $verified,
            $pending,
        ): array {
            $prefix = $customer->id.'|';

            $currencies = collect()
                ->merge($this->currenciesFor($orders, $prefix))
                ->merge($this->currenciesFor($verified, $prefix))
                ->merge($this->currenciesFor($pending, $prefix))
                ->unique()
                ->sort()
                ->values();

            $balances = $currencies->map(function (string $currency) use (
                $customer,
                $orders,
                $verified,
                $pending,
            ): array {
                $key = $customer->id.'|'.$currency;
                $receivable = round((float) ($orders[$key] ?? 0), 4);
                $verifiedAmount = round((float) ($verified[$key] ?? 0), 4);
                $pendingAmount = round((float) ($pending[$key] ?? 0), 4);

                return [
                    'currency' => $currency,
                    'receivable_total' => $receivable,
                    'verified_collections' => $verifiedAmount,
                    'pending_collections' => $pendingAmount,
                    'outstanding_balance' => round(
                        max(0, $receivable - $verifiedAmount),
                        4
                    ),
                ];
            })->all();

            return [
                'customer_id' => $customer->uuid,
                'customer_name' => $customer->name,
                'balances' => $balances,
            ];
        })->values()->all();
    }

    private function indexTotals(SupportCollection $rows): array
    {
        $indexed = [];

        foreach ($rows as $row) {
            $indexed[$row->customer_id.'|'.$row->currency] = (float) $row->total;
        }

        return $indexed;
    }

    private function currenciesFor(array $totals, string $prefix): array
    {
        $currencies = [];

        foreach (array_keys($totals) as $key) {
            if (str_starts_with($key, $prefix)) {
                $currencies[] = substr($key, strlen($prefix));
            }
        }

        return $currencies;
    }
}
