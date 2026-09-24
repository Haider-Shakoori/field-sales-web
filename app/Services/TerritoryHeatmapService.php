<?php

namespace App\Services;

use App\Models\Collection as PaymentCollection;
use App\Models\Customer;
use App\Models\CustomerVisit;
use App\Models\Order;
use App\Models\Territory;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class TerritoryHeatmapService
{
    public function build(CarbonImmutable $start, CarbonImmutable $end, string $metric, string $currency = 'AFN'): array
    {
        $territories = Territory::active()->with('branch')->orderBy('name')->get();
        $customers = Customer::active()->whereNotNull('territory_id')->get();
        $customerGroups = $customers->groupBy('territory_id');

        $visits = CustomerVisit::query()->whereBetween('checked_in_at', [$start, $end])->get()->groupBy(fn ($row) => $row->customer_id);
        $orders = Order::query()->where('status', 'approved')->where('currency', $currency)->whereBetween('ordered_at', [$start, $end])->get()->groupBy('customer_id');
        $collections = PaymentCollection::query()->where('status', 'verified')->where('currency', $currency)->whereBetween('collected_at', [$start, $end])->get()->groupBy('customer_id');

        $rows = $territories->map(function (Territory $territory) use ($customerGroups, $visits, $orders, $collections, $metric): array {
            $territoryCustomers = $customerGroups->get($territory->id, collect());
            $customerIds = $territoryCustomers->pluck('id');
            $visitCount = $customerIds->sum(fn ($id) => $visits->get($id, collect())->count());
            $sales = $customerIds->sum(fn ($id) => $orders->get($id, collect())->sum(fn ($order) => (float) $order->grand_total));
            $collectionAmount = $customerIds->sum(fn ($id) => $collections->get($id, collect())->sum(fn ($payment) => (float) $payment->amount));
            $points = $territoryCustomers->filter(fn (Customer $customer) => $customer->latitude !== null && $customer->longitude !== null);
            $lat = $points->isNotEmpty() ? $points->avg(fn ($c) => (float) $c->latitude) : null;
            $lng = $points->isNotEmpty() ? $points->avg(fn ($c) => (float) $c->longitude) : null;
            $value = match ($metric) {
                'visits' => $visitCount,
                'collections' => $collectionAmount,
                'customers' => $territoryCustomers->count(),
                default => $sales,
            };

            return [
                'id' => $territory->uuid,
                'code' => $territory->code,
                'name' => $territory->name,
                'branch' => $territory->branch?->name,
                'latitude' => $lat,
                'longitude' => $lng,
                'customers' => $territoryCustomers->count(),
                'visits' => $visitCount,
                'sales' => round($sales, 2),
                'collections' => round($collectionAmount, 2),
                'metric_value' => round((float) $value, 2),
            ];
        })->values();

        $max = max(1, (float) $rows->max('metric_value'));
        $rows = $rows->map(fn (array $row) => [...$row, 'intensity' => round($row['metric_value'] / $max, 4)]);

        return ['rows' => $rows, 'summary' => [
            'customers' => $rows->sum('customers'),
            'visits' => $rows->sum('visits'),
            'sales' => round((float) $rows->sum('sales'), 2),
            'collections' => round((float) $rows->sum('collections'), 2),
            'currency' => $currency,
        ]];
    }
}
