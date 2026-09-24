<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\Salesman;
use App\Models\Territory;
use App\Services\AuditLogger;
use App\Services\LeadPipelineService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LeadController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'stage' => ['nullable', Rule::in(Lead::STAGES)],
            'priority' => ['nullable', Rule::in(Lead::PRIORITIES)],
            'salesman' => ['nullable', 'uuid'],
            'search' => ['nullable', 'string', 'max:160'],
        ]);
        $salesman = ! empty($validated['salesman'])
            ? Salesman::where('uuid', $validated['salesman'])->firstOrFail()
            : null;
        $search = trim((string) ($validated['search'] ?? ''));

        $query = Lead::query()
            ->with(['assignedSalesman', 'territory', 'convertedCustomer'])
            ->when(! empty($validated['stage']), fn ($q) => $q->where('stage', $validated['stage']))
            ->when(! empty($validated['priority']), fn ($q) => $q->where('priority', $validated['priority']))
            ->when($salesman, fn ($q) => $q->where('assigned_salesman_id', $salesman->id))
            ->when($search !== '', function ($q) use ($search): void {
                $q->where(function ($nested) use ($search): void {
                    $nested->where('name', 'like', "%{$search}%")
                        ->orWhere('contact_person', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            });

        $leads = $query->orderByDesc('last_activity_at')->orderByDesc('id')->limit(300)->get();
        $stageCounts = Lead::query()->selectRaw('stage, COUNT(*) AS total')->groupBy('stage')->pluck('total', 'stage');

        return view('admin.leads.index', [
            'leads' => $leads,
            'groupedLeads' => $leads->groupBy('stage'),
            'stageCounts' => $stageCounts,
            'pipelineValue' => (float) Lead::whereNotIn('stage', ['won', 'lost'])->sum('estimated_value'),
            'wonValue' => (float) Lead::where('stage', 'won')->sum('estimated_value'),
            'salesmen' => Salesman::active()->orderBy('employee_code')->get(),
            'branches' => Branch::active()->orderBy('name')->get(),
            'territories' => Territory::active()->orderBy('name')->get(),
            'filters' => [
                'stage' => $validated['stage'] ?? null,
                'priority' => $validated['priority'] ?? null,
                'salesman' => $salesman?->uuid,
                'search' => $search,
            ],
            'stages' => Lead::STAGES,
            'priorities' => Lead::PRIORITIES,
            'sources' => Lead::SOURCES,
            'canManage' => $request->user()->hasPermission('leads:manage'),
        ]);
    }

    public function show(Request $request, Lead $lead): View
    {
        return view('admin.leads.show', [
            'lead' => $lead->load(['assignedSalesman', 'territory', 'branch', 'convertedCustomer', 'activities.user']),
            'salesmen' => Salesman::active()->orderBy('employee_code')->get(),
            'territories' => Territory::active()->orderBy('name')->get(),
            'stages' => Lead::STAGES,
            'priorities' => Lead::PRIORITIES,
            'activityTypes' => LeadActivity::TYPES,
            'canManage' => $request->user()->hasPermission('leads:manage'),
        ]);
    }

    public function store(Request $request, LeadPipelineService $pipeline, AuditLogger $audit): RedirectResponse
    {
        $validated = $this->validateLead($request);
        $lead = Lead::create([
            ...$validated,
            'currency' => strtoupper($validated['currency'] ?? 'AFN'),
            'stage' => $validated['stage'] ?? 'new',
            'probability' => $validated['probability'] ?? Lead::STAGE_PROBABILITIES[$validated['stage'] ?? 'new'],
            'created_by' => $request->user()->id,
            'last_activity_at' => now(),
        ]);
        $pipeline->activity($lead, $request->user(), 'note', 'Lead created.');
        $audit->record('lead.created', $lead, [], $this->auditValues($lead));

        return redirect()->route('admin.leads.show', $lead)->with('status', __('Lead created.'));
    }

    public function update(Request $request, Lead $lead, LeadPipelineService $pipeline, AuditLogger $audit): RedirectResponse
    {
        $validated = $this->validateLead($request, false);
        $before = $this->auditValues($lead);
        $oldStage = $lead->stage;
        $oldSalesman = $lead->assigned_salesman_id;
        $stage = $validated['stage'] ?? $lead->stage;

        $lead->update([
            ...$validated,
            'currency' => strtoupper($validated['currency'] ?? $lead->currency ?? 'AFN'),
            'probability' => $validated['probability'] ?? Lead::STAGE_PROBABILITIES[$stage] ?? $lead->probability,
            'lost_reason' => $stage === 'lost' ? ($validated['lost_reason'] ?? $lead->lost_reason) : null,
        ]);

        if ($oldStage !== $lead->stage) {
            $pipeline->activity($lead, $request->user(), 'status_change', 'Stage changed.', ['from' => $oldStage, 'to' => $lead->stage]);
        }
        if ((int) $oldSalesman !== (int) $lead->assigned_salesman_id) {
            $pipeline->activity($lead, $request->user(), 'assignment', 'Lead assignment changed.', ['from' => $oldSalesman, 'to' => $lead->assigned_salesman_id]);
        }

        $audit->record('lead.updated', $lead, $before, $this->auditValues($lead));

        return back()->with('status', __('Lead updated.'));
    }

    public function activity(Request $request, Lead $lead, LeadPipelineService $pipeline, AuditLogger $audit): RedirectResponse
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(LeadActivity::TYPES)],
            'notes' => ['required', 'string', 'max:5000'],
        ]);
        $activity = $pipeline->activity($lead, $request->user(), $validated['type'], $validated['notes']);
        $audit->record('lead.activity_created', $activity, [], ['lead_id' => $lead->id, 'type' => $activity->type]);

        return back()->with('status', __('Lead activity added.'));
    }

    public function convert(Request $request, Lead $lead, LeadPipelineService $pipeline, AuditLogger $audit): RedirectResponse
    {
        $before = $this->auditValues($lead);
        $customer = $pipeline->convert($lead, $request->user());
        $audit->record('lead.converted', $lead->fresh(), $before, [...$this->auditValues($lead->fresh()), 'customer_id' => $customer->id]);

        return redirect()->route('admin.customers.show', $customer)->with('status', __('Lead converted to customer.'));
    }

    private function validateLead(Request $request, bool $creating = true): array
    {
        $tenantId = $request->user()->tenant_id;
        $required = $creating ? 'required' : 'sometimes';

        return $request->validate([
            'name' => [$required, 'string', 'max:180'],
            'contact_person' => ['nullable', 'string', 'max:160'],
            'phone' => ['nullable', 'string', 'max:60'],
            'email' => ['nullable', 'email', 'max:191'],
            'address' => ['nullable', 'string', 'max:5000'],
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')->where('tenant_id', $tenantId)],
            'territory_id' => ['nullable', 'integer', Rule::exists('territories', 'id')->where('tenant_id', $tenantId)],
            'assigned_salesman_id' => ['nullable', 'integer', Rule::exists('salesmen', 'id')->where('tenant_id', $tenantId)],
            'source' => [$creating ? 'required' : 'sometimes', Rule::in(Lead::SOURCES)],
            'stage' => ['sometimes', Rule::in(Lead::STAGES)],
            'priority' => [$creating ? 'required' : 'sometimes', Rule::in(Lead::PRIORITIES)],
            'estimated_value' => ['nullable', 'numeric', 'min:0', 'max:99999999999999.9999'],
            'currency' => ['nullable', 'string', 'size:3'],
            'probability' => ['nullable', 'integer', 'between:0,100'],
            'expected_close_date' => ['nullable', 'date'],
            'lost_reason' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:10000'],
        ]);
    }

    private function auditValues(Lead $lead): array
    {
        return [
            'branch_id' => $lead->branch_id,
            'territory_id' => $lead->territory_id,
            'assigned_salesman_id' => $lead->assigned_salesman_id,
            'stage' => $lead->stage,
            'priority' => $lead->priority,
            'source' => $lead->source,
            'estimated_value' => $lead->estimated_value,
            'currency' => $lead->currency,
            'probability' => $lead->probability,
            'expected_close_date' => $lead->expected_close_date?->toDateString(),
            'converted_customer_id' => $lead->converted_customer_id,
        ];
    }
}