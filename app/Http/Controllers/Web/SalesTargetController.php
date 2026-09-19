<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Salesman;
use App\Models\SalesTarget;
use App\Services\AuditLogger;
use App\Services\TargetProgressService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SalesTargetController extends Controller
{
    public function index(Request $request, TargetProgressService $progress): View
    {
        $salesmanUuid = trim((string) $request->string('salesman'));
        $type = trim((string) $request->string('type'));

        $salesmen = Salesman::active()->orderBy('first_name')->orderBy('last_name')->get();

        $targets = SalesTarget::with(['salesman.user', 'tenant'])
            ->when($salesmanUuid !== '', function ($query) use ($salesmanUuid): void {
                $query->whereHas('salesman', fn ($salesman) => $salesman->where('uuid', $salesmanUuid));
            })
            ->when($type !== '', fn ($query) => $query->where('target_type', $type))
            ->orderByDesc('period_start')
            ->paginate(30)
            ->withQueryString();

        $progressById = collect($targets->items())
            ->mapWithKeys(fn (SalesTarget $target) => [$target->id => $progress->payload($target)]);

        return view('admin.targets.index', compact(
            'targets',
            'salesmen',
            'salesmanUuid',
            'type',
            'progressById',
        ));
    }

    public function create(): View
    {
        return view('admin.targets.form', [
            'target' => new SalesTarget(),
            'salesmen' => Salesman::active()->orderBy('first_name')->orderBy('last_name')->get(),
            'action' => route('admin.targets.store'),
            'method' => 'POST',
        ]);
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $data = $this->validated($request);
        $salesman = Salesman::where('uuid', $data['salesman_id'])->active()->firstOrFail();

        $this->assertUniqueWindow($salesman->id, $data);

        $target = SalesTarget::create([
            'salesman_id' => $salesman->id,
            'target_type' => $data['target_type'],
            'currency' => $this->currencyFor($data),
            'target_value' => round((float) $data['target_value'], 4),
            'period_start' => $data['period_start'],
            'period_end' => $data['period_end'],
            'notes' => $data['notes'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        $audit->record('target.created', $target, [], $target->only([
            'salesman_id',
            'target_type',
            'currency',
            'target_value',
            'period_start',
            'period_end',
        ]));

        return redirect()
            ->route('admin.targets.index')
            ->with('status', 'Sales target created.');
    }

    public function edit(Request $request, SalesTarget $target): View
    {
        $this->assertFuture($request, $target);

        return view('admin.targets.form', [
            'target' => $target,
            'salesmen' => Salesman::active()->orderBy('first_name')->orderBy('last_name')->get(),
            'action' => route('admin.targets.update', $target),
            'method' => 'PUT',
        ]);
    }

    public function update(
        Request $request,
        SalesTarget $target,
        AuditLogger $audit,
    ): RedirectResponse {
        $this->assertFuture($request, $target);

        $data = $this->validated($request);
        $salesman = Salesman::where('uuid', $data['salesman_id'])->active()->firstOrFail();

        $this->assertUniqueWindow($salesman->id, $data, $target->id);

        $before = $target->only([
            'salesman_id',
            'target_type',
            'currency',
            'target_value',
            'period_start',
            'period_end',
            'notes',
        ]);

        $target->update([
            'salesman_id' => $salesman->id,
            'target_type' => $data['target_type'],
            'currency' => $this->currencyFor($data),
            'target_value' => round((float) $data['target_value'], 4),
            'period_start' => $data['period_start'],
            'period_end' => $data['period_end'],
            'notes' => $data['notes'] ?? null,
        ]);

        $audit->record('target.updated', $target, $before, $target->only([
            'salesman_id',
            'target_type',
            'currency',
            'target_value',
            'period_start',
            'period_end',
            'notes',
        ]));

        return redirect()
            ->route('admin.targets.index')
            ->with('status', 'Sales target updated.');
    }

    public function destroy(
        Request $request,
        SalesTarget $target,
        AuditLogger $audit,
    ): RedirectResponse {
        $this->assertFuture($request, $target);

        $audit->record('target.deleted', $target, $target->only([
            'salesman_id',
            'target_type',
            'currency',
            'target_value',
            'period_start',
            'period_end',
        ]), []);

        $target->delete();

        return redirect()
            ->route('admin.targets.index')
            ->with('status', 'Sales target deleted.');
    }

    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'salesman_id' => ['required', 'uuid'],
            'target_type' => ['required', Rule::in(SalesTarget::TYPES)],
            'currency' => ['nullable', 'string', 'size:3', 'regex:/^[A-Za-z]{3}$/'],
            'target_value' => ['required', 'numeric', 'gt:0', 'max:999999999999.9999'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        if (
            in_array($validated['target_type'], SalesTarget::AMOUNT_TYPES, true)
            && empty($validated['currency'])
        ) {
            throw ValidationException::withMessages([
                'currency' => 'Currency is required for amount targets.',
            ]);
        }

        if (
            ! in_array($validated['target_type'], SalesTarget::AMOUNT_TYPES, true)
            && floor((float) $validated['target_value']) !== (float) $validated['target_value']
        ) {
            throw ValidationException::withMessages([
                'target_value' => 'Count targets must be whole numbers.',
            ]);
        }

        return $validated;
    }

    private function currencyFor(array $data): ?string
    {
        return in_array($data['target_type'], SalesTarget::AMOUNT_TYPES, true)
            ? strtoupper($data['currency'])
            : null;
    }

    private function assertUniqueWindow(
        int $salesmanId,
        array $data,
        ?int $ignoreId = null,
    ): void {
        $exists = SalesTarget::query()
            ->where('salesman_id', $salesmanId)
            ->where('target_type', $data['target_type'])
            ->whereDate('period_start', $data['period_start'])
            ->whereDate('period_end', $data['period_end'])
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'target_type' => 'A target of this type already exists for this salesman and period.',
            ]);
        }
    }

    private function assertFuture(Request $request, SalesTarget $target): void
    {
        $timezone = $request->user()->tenant->timezone ?: 'UTC';
        $today = now($timezone)->toDateString();

        if ($target->period_start->toDateString() <= $today) {
            abort(409, 'Active or historical targets are immutable.');
        }
    }
}
