<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerFollowUp;
use App\Models\Salesman;
use App\Services\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CustomerFollowUpController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::in(CustomerFollowUp::STATUSES)],
            'salesman' => ['nullable', 'uuid'],
        ]);

        $status = $validated['status'] ?? 'pending';
        $salesman = isset($validated['salesman']) && $validated['salesman'] !== ''
            ? Salesman::where('uuid', $validated['salesman'])->firstOrFail()
            : null;
        $user = $request->user()->loadMissing('tenant');
        $timezone = $user->tenant?->timezone ?: config('app.timezone', 'UTC');

        return view('admin.follow-ups.index', [
            'followUps' => CustomerFollowUp::with(['customer', 'assignedSalesman.user'])
                ->where('status', $status)
                ->when(
                    $salesman,
                    fn ($query) => $query->where('assigned_salesman_id', $salesman->id)
                )
                ->orderBy('due_at')
                ->paginate(40)
                ->withQueryString(),
            'salesmen' => Salesman::active()->orderBy('employee_code')->get(),
            'filters' => [
                'status' => $status,
                'salesman' => $salesman?->uuid,
            ],
            'timezone' => $timezone,
        ]);
    }

    public function store(
        Request $request,
        Customer $customer,
        AuditLogger $audit,
    ): RedirectResponse {
        $tenantId = $request->user()->tenant_id;
        $validated = $request->validate([
            'assigned_salesman_id' => [
                'nullable',
                'integer',
                Rule::exists('salesmen', 'id')->where('tenant_id', $tenantId),
            ],
            'type' => ['required', Rule::in(CustomerFollowUp::TYPES)],
            'priority' => ['required', Rule::in(CustomerFollowUp::PRIORITIES)],
            'due_at' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $user = $request->user()->loadMissing('tenant');
        $timezone = $user->tenant?->timezone ?: config('app.timezone', 'UTC');

        $followUp = $customer->followUps()->create([
            'assigned_salesman_id' => $validated['assigned_salesman_id'] ?? null,
            'type' => $validated['type'],
            'priority' => $validated['priority'],
            'status' => 'pending',
            'due_at' => CarbonImmutable::parse($validated['due_at'], $timezone)->utc(),
            'notes' => $validated['notes'] ?? null,
            'created_by' => $user->id,
        ]);

        $audit->record('customer_follow_up.created', $followUp, [], [
            'customer_id' => $customer->id,
            'assigned_salesman_id' => $followUp->assigned_salesman_id,
            'type' => $followUp->type,
            'priority' => $followUp->priority,
            'due_at' => $followUp->due_at?->toISOString(),
        ]);

        return back()->with('status', __('Follow-up scheduled.'));
    }

    public function updateStatus(
        Request $request,
        CustomerFollowUp $followUp,
        AuditLogger $audit,
    ): RedirectResponse {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['completed', 'cancelled'])],
            'completion_note' => ['nullable', 'string', 'max:5000'],
        ]);

        $before = [
            'status' => $followUp->status,
            'completed_at' => $followUp->completed_at?->toISOString(),
            'completion_note' => $followUp->completion_note,
        ];

        $completed = $validated['status'] === 'completed';

        $followUp->update([
            'status' => $validated['status'],
            'completed_at' => $completed ? now() : null,
            'completed_by' => $completed ? $request->user()->id : null,
            'completion_note' => $validated['completion_note'] ?? null,
        ]);

        $audit->record('customer_follow_up.status_changed', $followUp, $before, [
            'status' => $followUp->status,
            'completed_at' => $followUp->completed_at?->toISOString(),
            'completion_note' => $followUp->completion_note,
        ]);

        return back()->with('status', __('Follow-up updated.'));
    }
}
