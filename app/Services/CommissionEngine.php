<?php

namespace App\Services;

use App\Models\Collection as PaymentCollection;
use App\Models\CommissionEarning;
use App\Models\CommissionPlan;
use App\Models\Order;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class CommissionEngine
{
    public function recalculate(CarbonImmutable $start, CarbonImmutable $end): array
    {
        $created = 0;
        $total = 0.0;
        $plans = CommissionPlan::query()->with('salesmen')->where('is_active', true)
            ->whereDate('effective_from', '<=', $end->toDateString())
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $start->toDateString()))
            ->get();

        foreach ($plans as $plan) {
            CommissionEarning::where('commission_plan_id', $plan->id)->where('status', 'earned')->whereBetween('earned_at', [$start, $end])->delete();
            $salesmanIds = $plan->salesmen->pluck('id');
            if ($salesmanIds->isEmpty()) continue;

            if ($plan->metric === 'sales') {
                $sources = Order::query()->with(['items','customer'])->whereIn('salesman_id', $salesmanIds)->where('status','approved')->where('currency',$plan->currency)->whereBetween('ordered_at',[$start,$end])->get();
                foreach ($sources as $order) {
                    if ($plan->territory_id && (int) $order->customer?->territory_id !== (int) $plan->territory_id) continue;
                    $base = $plan->product_id
                        ? (float) $order->items->where('product_id', $plan->product_id)->sum(fn ($item)=>(float)$item->line_total)
                        : (float) $order->grand_total;
                    $created += $this->accrue($plan, $order->salesman_id, 'order', $order->id, $order->uuid, $base, $order->ordered_at, $total);
                }
            } else {
                if ($plan->product_id) continue;
                $sources = PaymentCollection::query()->with('customer')->whereIn('salesman_id',$salesmanIds)->where('status','verified')->where('currency',$plan->currency)->whereBetween('collected_at',[$start,$end])->get();
                foreach ($sources as $collection) {
                    if ($plan->territory_id && (int) $collection->customer?->territory_id !== (int) $plan->territory_id) continue;
                    $created += $this->accrue($plan, $collection->salesman_id, 'collection', $collection->id, $collection->uuid, (float)$collection->amount, $collection->collected_at, $total);
                }
            }
        }

        return ['created'=>$created,'total'=>round($total,4)];
    }

    private function accrue(CommissionPlan $plan, int $salesmanId, string $sourceType, int $sourceId, ?string $sourceUuid, float $base, $earnedAt, float &$total): int
    {
        if ($base <= 0 || $base < (float) $plan->minimum_source_amount) return 0;
        $amount = round($base * ((float)$plan->rate_percent / 100), 4);
        if ($amount <= 0) return 0;
        CommissionEarning::updateOrCreate(
            ['commission_plan_id'=>$plan->id,'salesman_id'=>$salesmanId,'source_type'=>$sourceType,'source_id'=>$sourceId],
            ['source_uuid'=>$sourceUuid,'source_amount'=>$base,'rate_percent'=>$plan->rate_percent,'commission_amount'=>$amount,'currency'=>$plan->currency,'earned_at'=>$earnedAt,'status'=>'earned']
        );
        $total += $amount;
        return 1;
    }
}