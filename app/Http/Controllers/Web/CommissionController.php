<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\CommissionRule;
use App\Models\CommissionRun;
use App\Models\Product;
use App\Models\Salesman;
use App\Models\SalesTarget;
use App\Models\Territory;
use App\Services\AuditLogger;
use App\Services\CommissionEngine;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CommissionController extends Controller
{
    public function index(Request $request): View
    {
        return view('admin.commissions.index', [
            'rules' => CommissionRule::query()
                ->with(['salesman', 'territory', 'product'])
                ->orderByDesc('is_active')
                ->orderBy('name')
                ->paginate(25, ['*'], 'rules_page')
                ->withQueryString(),
            'runs' => CommissionRun::query()
                ->with(['generator', 'approver'])
                ->withCount('lines')
                ->latest('period_start')
                ->paginate(20, ['*'], 'runs_page')
                ->withQueryString(),
            'canManage' => $request->user()->hasPermission('commissions:manage'),
        ]);
    }

    public function createRule(): View
    {
        return view('admin.commissions.rule-form', [
            ...$this->formData(),
            'rule' => new CommissionRule,
            'action' => route('admin.commissions.rules.store'),
            'method' => 'POST',
        ]);
    }

    public function storeRule(Request $request, AuditLogger $audit): RedirectResponse
    {
        $data = $this->validatedRule($request);
        $rule = CommissionRule::create([
            ...$this->ruleAttributes($data),
            'created_by' => $request->user()->id,
        ]);

        $audit->record('commission_rule.created', $rule, [], $this->auditRule($rule));

        return redirect()
            ->route('admin.commissions.index')
            ->with('status', 'Commission rule created.');
    }

    public function editRule(CommissionRule $rule): View
    {
        return view('admin.commissions.rule-form', [
            ...$this->formData(),
            'rule' => $rule,
            'action' => route('admin.commissions.rules.update', $rule),
            'method' => 'PUT',
        ]);
    }

    public function updateRule(
        Request $request,
        CommissionRule $rule,
        AuditLogger $audit,
    ): RedirectResponse {
        $data = $this->validatedRule($request, $rule);
        $before = $this->auditRule($rule);
        $rule->update($this->ruleAttributes($data));
        $audit->record('commission_rule.updated', $rule, $before, $this->auditRule($rule));

        return redirect()
            ->route('admin.commissions.index')
            ->with('status', 'Commission rule updated.');
    }

    public function destroyRule(CommissionRule $rule, AuditLogger $audit): RedirectResponse
    {
        if ($rule->runLines()->exists()) {
            throw ValidationException::withMessages([
                'rule' => 'A rule referenced by a commission run cannot be deleted. Deactivate it instead.',
            ]);
        }

        $before = $this->auditRule($rule);
        $audit->record('commission_rule.deleted', $rule, $before, []);
        $rule->delete();

        return redirect()
            ->route('admin.commissions.index')
            ->with('status', 'Commission rule deleted.');
    }

    public function generate(
        Request $request,
        CommissionEngine $engine,
        AuditLogger $audit,
    ): RedirectResponse {
        $validated = $request->validate([
            'period_start' => ['required', 'date_format:Y-m-d'],
            'period_end' => ['required', 'date_format:Y-m-d', 'after_or_equal:period_start'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        if (
            CarbonImmutable::parse($validated['period_start'])
                ->diffInDays(CarbonImmutable::parse($validated['period_end'])) > 366
        ) {
            throw ValidationException::withMessages([
                'period_end' => 'Commission periods cannot exceed 366 days.',
            ]);
        }

        $run = $engine->generate(
            $request->user(),
            $validated['period_start'],
            $validated['period_end'],
            $validated['notes'] ?? null,
        );

        $audit->record('commission_run.generated', $run, [], [
            'period_start' => $run->period_start->toDateString(),
            'period_end' => $run->period_end->toDateString(),
            'line_count' => $run->lines->count(),
        ]);

        return redirect()
            ->route('admin.commissions.runs.show', $run)
            ->with('status', 'Commission draft generated.');
    }

    public function show(
        Request $request,
        CommissionRun $run,
        CommissionEngine $engine,
    ): View {
        $run->load([
            'lines.rule',
            'lines.salesman',
            'generator',
            'approver',
        ]);

        return view('admin.commissions.show', [
            'run' => $run,
            'totals' => $engine->totals($run),
            'canManage' => $request->user()->hasPermission('commissions:manage'),
        ]);
    }

    public function approve(
        Request $request,
        CommissionRun $run,
        AuditLogger $audit,
    ): RedirectResponse {
        if ($run->state === 'approved') {
            return back()->with('status', 'Commission run is already approved.');
        }

        DB::transaction(function () use ($request, $run, $audit): void {
            $locked = CommissionRun::whereKey($run->id)->lockForUpdate()->firstOrFail();

            if ($locked->state === 'approved') {
                return;
            }

            $overlap = CommissionRun::query()
                ->whereKeyNot($locked->id)
                ->where('state', 'approved')
                ->whereDate('period_start', '<=', $locked->period_end)
                ->whereDate('period_end', '>=', $locked->period_start)
                ->exists();

            if ($overlap) {
                throw ValidationException::withMessages([
                    'run' => 'This period overlaps another approved commission run.',
                ]);
            }

            $before = ['state' => $locked->state];
            $locked->update([
                'state' => 'approved',
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
            ]);

            $audit->record('commission_run.approved', $locked, $before, [
                'state' => 'approved',
                'approved_at' => $locked->approved_at?->toISOString(),
            ]);
        });

        return back()->with('status', 'Commission run approved and locked.');
    }

    private function formData(): array
    {
        return [
            'basisTypes' => CommissionRule::BASIS_TYPES,
            'rewardTypes' => CommissionRule::REWARD_TYPES,
            'targetTypes' => SalesTarget::TYPES,
            'salesmen' => Salesman::active()->orderBy('first_name')->orderBy('last_name')->get(),
            'territories' => Territory::active()->orderBy('name')->get(),
            'products' => Product::active()->orderBy('name')->get(),
        ];
    }

    private function validatedRule(Request $request, ?CommissionRule $rule = null): array
    {
        $tenantId = $request->user()->tenant_id;
        $validated = $request->validate([
            'code' => [
                'required',
                'string',
                'max:80',
                'regex:/^[A-Za-z0-9_-]+$/',
                Rule::unique('commission_rules', 'code')
                    ->where('tenant_id', $tenantId)
                    ->ignore($rule?->id),
            ],
            'name' => ['required', 'string', 'max:180'],
            'basis_type' => ['required', Rule::in(CommissionRule::BASIS_TYPES)],
            'reward_type' => ['required', Rule::in(CommissionRule::REWARD_TYPES)],
            'rate' => ['required', 'numeric', 'gt:0', 'max:999999999999.9999'],
            'currency' => ['nullable', 'string', 'size:3', 'regex:/^[A-Za-z]{3}$/'],
            'minimum_basis' => ['nullable', 'numeric', 'gte:0', 'max:999999999999.9999'],
            'salesman_id' => [
                'nullable',
                'integer',
                Rule::exists('salesmen', 'id')->where('tenant_id', $tenantId),
            ],
            'territory_id' => [
                'nullable',
                'integer',
                Rule::exists('territories', 'id')->where('tenant_id', $tenantId),
            ],
            'product_id' => [
                'nullable',
                'integer',
                Rule::exists('products', 'id')->where('tenant_id', $tenantId),
            ],
            'target_type' => ['nullable', Rule::in(SalesTarget::TYPES)],
            'effective_from' => ['required', 'date_format:Y-m-d'],
            'effective_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:effective_from'],
            'is_active' => ['required', 'boolean'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        if (
            $validated['reward_type'] === 'percentage'
            && (float) $validated['rate'] > 100
        ) {
            throw ValidationException::withMessages([
                'rate' => 'Percentage commission cannot exceed 100%.',
            ]);
        }

        if ($validated['reward_type'] === 'fixed' && empty($validated['currency'])) {
            throw ValidationException::withMessages([
                'currency' => 'Currency is required for fixed commission rewards.',
            ]);
        }

        if (
            $validated['basis_type'] === 'product_sales_amount'
            && empty($validated['product_id'])
        ) {
            throw ValidationException::withMessages([
                'product_id' => 'Product is required for product sales commission rules.',
            ]);
        }

        if ($validated['basis_type'] === 'target_achievement') {
            if ($validated['reward_type'] !== 'fixed') {
                throw ValidationException::withMessages([
                    'reward_type' => 'Target achievement rules use a fixed reward per achieved target.',
                ]);
            }

            if (empty($validated['currency'])) {
                throw ValidationException::withMessages([
                    'currency' => 'Currency is required for target achievement rewards.',
                ]);
            }
        }

        return $validated;
    }

    private function ruleAttributes(array $data): array
    {
        $basisType = $data['basis_type'];

        return [
            'code' => strtoupper($data['code']),
            'name' => $data['name'],
            'basis_type' => $basisType,
            'reward_type' => $data['reward_type'],
            'rate' => round((float) $data['rate'], 4),
            'currency' => empty($data['currency']) ? null : strtoupper($data['currency']),
            'minimum_basis' => $data['minimum_basis'] === null
                ? null
                : round((float) $data['minimum_basis'], 4),
            'salesman_id' => $data['salesman_id'] ?? null,
            'territory_id' => $basisType === 'target_achievement'
                ? null
                : ($data['territory_id'] ?? null),
            'product_id' => $basisType === 'product_sales_amount'
                ? $data['product_id']
                : null,
            'target_type' => $basisType === 'target_achievement'
                ? ($data['target_type'] ?? null)
                : null,
            'effective_from' => $data['effective_from'],
            'effective_to' => $data['effective_to'] ?? null,
            'is_active' => (bool) $data['is_active'],
            'notes' => $data['notes'] ?? null,
        ];
    }

    private function auditRule(CommissionRule $rule): array
    {
        return $rule->only([
            'code',
            'name',
            'basis_type',
            'reward_type',
            'rate',
            'currency',
            'minimum_basis',
            'salesman_id',
            'territory_id',
            'product_id',
            'target_type',
            'effective_from',
            'effective_to',
            'is_active',
        ]);
    }
}
