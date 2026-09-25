<?php

namespace App\Services;

use App\Models\Collection as CustomerCollection;
use App\Models\CommissionRule;
use App\Models\CommissionRun;
use App\Models\CommissionRunLine;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Salesman;
use App\Models\SalesTarget;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CommissionEngine
{
    public function __construct(
        private readonly TenantClock $clock,
        private readonly TargetProgressService $targetProgress,
    ) {}

    public function generate(
        User $actor,
        string $fromDate,
        string $toDate,
        ?string $notes = null,
    ): CommissionRun {
        $actor->loadMissing('tenant');
        $timezone = $this->clock->timezone($actor->tenant);
        $start = CarbonImmutable::parse($fromDate.' 00:00:00', $timezone)->utc();
        $end = CarbonImmutable::parse($toDate.' 00:00:00', $timezone)->addDay()->utc();

        return DB::transaction(function () use (
            $actor,
            $fromDate,
            $toDate,
            $start,
            $end,
            $notes,
        ): CommissionRun {
            $run = CommissionRun::query()
                ->whereDate('period_start', $fromDate)
                ->whereDate('period_end', $toDate)
                ->lockForUpdate()
                ->first();

            if ($run?->state === 'approved') {
                throw ValidationException::withMessages([
                    'period_start' => 'This commission period is already approved and immutable.',
                ]);
            }

            $overlap = CommissionRun::query()
                ->where('state', 'approved')
                ->whereDate('period_start', '<=', $toDate)
                ->whereDate('period_end', '>=', $fromDate)
                ->when($run, fn (Builder $query) => $query->whereKeyNot($run->id))
                ->exists();

            if ($overlap) {
                throw ValidationException::withMessages([
                    'period_start' => 'This period overlaps an approved commission run.',
                ]);
            }

            if (! $run) {
                $run = CommissionRun::create([
                    'period_start' => $fromDate,
                    'period_end' => $toDate,
                    'state' => 'draft',
                    'generated_by' => $actor->id,
                    'generated_at' => now(),
                    'notes' => $notes,
                ]);
            } else {
                $run->update([
                    'generated_by' => $actor->id,
                    'generated_at' => now(),
                    'notes' => $notes,
                ]);
                $run->lines()->delete();
            }

            $rules = CommissionRule::query()
                ->with(['salesman', 'territory', 'product'])
                ->where('is_active', true)
                ->effectiveDuring($fromDate, $toDate)
                ->orderBy('id')
                ->get();

            $activeSalesmen = Salesman::active()
                ->orderBy('id')
                ->get()
                ->keyBy('id');

            foreach ($rules as $rule) {
                $salesmen = $rule->salesman_id
                    ? $activeSalesmen->only([$rule->salesman_id])
                    : $activeSalesmen;

                foreach ($salesmen as $salesman) {
                    $this->calculateRule(
                        $run,
                        $rule,
                        $salesman,
                        $start,
                        $end,
                        $fromDate,
                        $toDate,
                    );
                }
            }

            return $run->fresh([
                'lines.rule',
                'lines.salesman',
                'generator',
                'approver',
            ]);
        });
    }

    public function totals(CommissionRun $run): array
    {
        return $run->lines
            ->groupBy('currency')
            ->map(fn (Collection $lines, string $currency) => [
                'currency' => $currency,
                'basis' => round($lines->sum(fn (CommissionRunLine $line) => (float) $line->basis_value), 4),
                'commission' => round($lines->sum(fn (CommissionRunLine $line) => (float) $line->commission_amount), 4),
                'lines' => $lines->count(),
            ])
            ->sortKeys()
            ->values()
            ->all();
    }

    private function calculateRule(
        CommissionRun $run,
        CommissionRule $rule,
        Salesman $salesman,
        CarbonImmutable $start,
        CarbonImmutable $end,
        string $fromDate,
        string $toDate,
    ): void {
        if ($rule->basis_type === 'target_achievement') {
            $this->targetAchievement(
                $run,
                $rule,
                $salesman,
                $fromDate,
                $toDate,
            );

            return;
        }

        $rows = match ($rule->basis_type) {
            'sales_amount' => $this->salesBasis($rule, $salesman, $start, $end),
            'collections_amount' => $this->collectionBasis($rule, $salesman, $start, $end),
            'product_sales_amount' => $this->productBasis($rule, $salesman, $start, $end),
            default => collect(),
        };

        foreach ($rows as $row) {
            $basis = (float) $row->basis_value;
            if ($basis <= 0 || ! $this->meetsMinimum($rule, $basis)) {
                continue;
            }

            $commission = $rule->reward_type === 'percentage'
                ? round($basis * ((float) $rule->rate / 100), 4)
                : round((float) $rule->rate, 4);

            if ($commission <= 0) {
                continue;
            }

            $this->writeLine(
                run: $run,
                rule: $rule,
                salesman: $salesman,
                currency: strtoupper((string) $row->currency),
                basis: $basis,
                commission: $commission,
                evidence: [
                    'record_count' => (int) $row->record_count,
                    'territory' => $rule->territory?->name,
                    'product' => $rule->product?->name,
                    'minimum_basis' => $rule->minimum_basis === null
                        ? null
                        : (float) $rule->minimum_basis,
                    'rules_stack' => true,
                ],
            );
        }
    }

    private function salesBasis(
        CommissionRule $rule,
        Salesman $salesman,
        CarbonImmutable $start,
        CarbonImmutable $end,
    ): Collection {
        return Order::query()
            ->where('salesman_id', $salesman->id)
            ->where('status', 'approved')
            ->where('ordered_at', '>=', $start)
            ->where('ordered_at', '<', $end)
            ->when($rule->currency, fn (Builder $query, string $currency) => $query->where('currency', $currency))
            ->when(
                $rule->territory_id,
                fn (Builder $query, int $territoryId) => $query->whereHas(
                    'customer',
                    fn (Builder $customer) => $customer->where('territory_id', $territoryId),
                ),
            )
            ->selectRaw('currency, COUNT(*) AS record_count, SUM(grand_total) AS basis_value')
            ->groupBy('currency')
            ->get();
    }

    private function collectionBasis(
        CommissionRule $rule,
        Salesman $salesman,
        CarbonImmutable $start,
        CarbonImmutable $end,
    ): Collection {
        return CustomerCollection::query()
            ->where('salesman_id', $salesman->id)
            ->where('status', 'verified')
            ->where('collected_at', '>=', $start)
            ->where('collected_at', '<', $end)
            ->when($rule->currency, fn (Builder $query, string $currency) => $query->where('currency', $currency))
            ->when(
                $rule->territory_id,
                fn (Builder $query, int $territoryId) => $query->whereHas(
                    'customer',
                    fn (Builder $customer) => $customer->where('territory_id', $territoryId),
                ),
            )
            ->selectRaw('currency, COUNT(*) AS record_count, SUM(amount) AS basis_value')
            ->groupBy('currency')
            ->get();
    }

    private function productBasis(
        CommissionRule $rule,
        Salesman $salesman,
        CarbonImmutable $start,
        CarbonImmutable $end,
    ): Collection {
        $query = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('customers', 'customers.id', '=', 'orders.customer_id')
            ->where('orders.salesman_id', $salesman->id)
            ->where('orders.status', 'approved')
            ->where('orders.ordered_at', '>=', $start)
            ->where('orders.ordered_at', '<', $end)
            ->where('order_items.product_id', $rule->product_id);

        if ($rule->currency) {
            $query->where('orders.currency', $rule->currency);
        }

        if ($rule->territory_id) {
            $query->where('customers.territory_id', $rule->territory_id);
        }

        return $query
            ->selectRaw('orders.currency AS currency, COUNT(*) AS record_count, SUM(order_items.line_total) AS basis_value')
            ->groupBy('orders.currency')
            ->get();
    }

    private function targetAchievement(
        CommissionRun $run,
        CommissionRule $rule,
        Salesman $salesman,
        string $fromDate,
        string $toDate,
    ): void {
        $targets = SalesTarget::query()
            ->with(['salesman.user', 'tenant'])
            ->where('salesman_id', $salesman->id)
            ->whereDate('period_start', '>=', $fromDate)
            ->whereDate('period_end', '<=', $toDate)
            ->when(
                $rule->target_type,
                fn (Builder $query, string $type) => $query->where('target_type', $type),
            )
            ->get();

        $achieved = $targets
            ->map(fn (SalesTarget $target) => $this->targetProgress->payload($target))
            ->filter(fn (array $payload) => (float) $payload['progress_percent'] >= 100)
            ->values();

        $count = $achieved->count();
        if ($count === 0 || ! $this->meetsMinimum($rule, (float) $count)) {
            return;
        }

        $commission = round((float) $rule->rate * $count, 4);
        if ($commission <= 0) {
            return;
        }

        $this->writeLine(
            run: $run,
            rule: $rule,
            salesman: $salesman,
            currency: strtoupper((string) $rule->currency),
            basis: (float) $count,
            commission: $commission,
            evidence: [
                'achieved_targets' => $achieved->map(fn (array $payload) => [
                    'id' => $payload['id'],
                    'target_type' => $payload['target_type'],
                    'progress_percent' => $payload['progress_percent'],
                    'period_start' => $payload['period_start'],
                    'period_end' => $payload['period_end'],
                ])->take(50)->all(),
                'reward_per_achieved_target' => (float) $rule->rate,
                'rules_stack' => true,
            ],
        );
    }

    private function meetsMinimum(CommissionRule $rule, float $basis): bool
    {
        return $rule->minimum_basis === null
            || $basis >= (float) $rule->minimum_basis;
    }

    private function writeLine(
        CommissionRun $run,
        CommissionRule $rule,
        Salesman $salesman,
        string $currency,
        float $basis,
        float $commission,
        array $evidence,
    ): void {
        CommissionRunLine::create([
            'commission_run_id' => $run->id,
            'commission_rule_id' => $rule->id,
            'salesman_id' => $salesman->id,
            'basis_type' => $rule->basis_type,
            'reward_type' => $rule->reward_type,
            'currency' => $currency,
            'basis_value' => round($basis, 4),
            'rate' => round((float) $rule->rate, 4),
            'commission_amount' => round($commission, 4),
            'evidence' => $evidence,
        ]);
    }
}
