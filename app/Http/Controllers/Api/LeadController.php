<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Services\AuditLogger;
use App\Services\LeadPipelineService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LeadController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        [$user, $salesman] = $this->salesman($request, 'leads:view');
        $validated = $request->validate([
            'stage' => ['nullable', Rule::in(Lead::STAGES)],
        ]);

        $rows = Lead::query()
            ->with(['convertedCustomer', 'territory'])
            ->where('assigned_salesman_id', $salesman->id)
            ->when(! empty($validated['stage']), fn ($q) => $q->where('stage', $validated['stage']))
            ->orderByDesc('last_activity_at')
            ->orderByDesc('id')
            ->limit(300)
            ->get()
            ->map(fn (Lead $lead) => $this->payload($lead))
            ->values()
            ->all();

        return ApiResponse::success($rows);
    }

    public function show(Request $request, Lead $lead): JsonResponse
    {
        [, $salesman] = $this->salesman($request, 'leads:view');
        abort_unless((int) $lead->assigned_salesman_id === (int) $salesman->id, 404);

        return ApiResponse::success([
            ...$this->payload($lead->load(['convertedCustomer', 'territory'])),
            'activities' => $lead->activities()->with('user')->limit(100)->get()->map(fn (LeadActivity $activity) => $this->activityPayload($activity))->all(),
        ]);
    }

    public function store(Request $request, LeadPipelineService $pipeline, AuditLogger $audit): JsonResponse
    {
        [$user, $salesman] = $this->salesman($request, 'leads:manage');
        $validated = $request->validate([
            'offline_uuid' => ['required', 'uuid'],
            'name' => ['required', 'string', 'max:180'],
            'contact_person' => ['nullable', 'string', 'max:160'],
            'phone' => ['nullable', 'string', 'max:60'],
            'email' => ['nullable', 'email', 'max:191'],
            'address' => ['nullable', 'string', 'max:5000'],
            'source' => ['required', Rule::in(Lead::SOURCES)],
            'stage' => ['nullable', Rule::in(Lead::STAGES)],
            'priority' => ['required', Rule::in(Lead::PRIORITIES)],
            'estimated_value' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'expected_close_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:10000'],
        ]);

        $existing = Lead::where('uuid', $validated['offline_uuid'])->first();
        if ($existing) {
            abort_unless((int) $existing->assigned_salesman_id === (int) $salesman->id, 409);

            return ApiResponse::success($this->payload($existing->load(['convertedCustomer', 'territory'])));
        }

        $assignment = $salesman->assignments()->whereNull('effective_to')->latest('effective_from')->first();
        $stage = $validated['stage'] ?? 'new';
        $lead = Lead::create([
            'uuid' => $validated['offline_uuid'],
            'branch_id' => $assignment?->branch_id ?? $user->branch_id,
            'territory_id' => $assignment?->territory_id,
            'assigned_salesman_id' => $salesman->id,
            'name' => trim($validated['name']),
            'contact_person' => $validated['contact_person'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'email' => $validated['email'] ?? null,
            'address' => $validated['address'] ?? null,
            'source' => $validated['source'],
            'stage' => $stage,
            'priority' => $validated['priority'],
            'estimated_value' => $validated['estimated_value'] ?? null,
            'currency' => strtoupper($validated['currency'] ?? 'AFN'),
            'probability' => Lead::STAGE_PROBABILITIES[$stage],
            'expected_close_date' => $validated['expected_close_date'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'last_activity_at' => now(),
            'created_by' => $user->id,
        ]);
        $pipeline->activity($lead, $user, 'note', 'Lead captured in the mobile app.');
        $audit->record('lead.created_mobile', $lead, [], ['stage' => $lead->stage, 'assigned_salesman_id' => $salesman->id]);

        return ApiResponse::success($this->payload($lead->load(['convertedCustomer', 'territory'])), 201);
    }

    public function update(Request $request, Lead $lead, LeadPipelineService $pipeline, AuditLogger $audit): JsonResponse
    {
        [$user, $salesman] = $this->salesman($request, 'leads:manage');
        abort_unless((int) $lead->assigned_salesman_id === (int) $salesman->id, 404);
        $validated = $request->validate([
            'stage' => ['nullable', Rule::in(Lead::STAGES)],
            'priority' => ['nullable', Rule::in(Lead::PRIORITIES)],
            'estimated_value' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'expected_close_date' => ['nullable', 'date'],
            'lost_reason' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:10000'],
        ]);
        $beforeStage = $lead->stage;
        $lead->update(array_filter([
            'priority' => $validated['priority'] ?? null,
            'estimated_value' => array_key_exists('estimated_value', $validated) ? $validated['estimated_value'] : null,
            'currency' => isset($validated['currency']) ? strtoupper($validated['currency']) : null,
            'expected_close_date' => array_key_exists('expected_close_date', $validated) ? $validated['expected_close_date'] : null,
            'notes' => array_key_exists('notes', $validated) ? $validated['notes'] : null,
        ], fn ($value) => $value !== null));
        if (! empty($validated['stage']) && $validated['stage'] !== $beforeStage) {
            $pipeline->applyStage($lead, $user, $validated['stage'], $validated['lost_reason'] ?? null);
        }
        $audit->record('lead.updated_mobile', $lead, ['stage' => $beforeStage], ['stage' => $lead->stage]);

        return ApiResponse::success($this->payload($lead->fresh()->load(['convertedCustomer', 'territory'])));
    }

    public function activity(Request $request, Lead $lead, LeadPipelineService $pipeline): JsonResponse
    {
        [$user, $salesman] = $this->salesman($request, 'leads:manage');
        abort_unless((int) $lead->assigned_salesman_id === (int) $salesman->id, 404);
        $validated = $request->validate([
            'offline_uuid' => ['required', 'uuid'],
            'type' => ['required', Rule::in(LeadActivity::TYPES)],
            'notes' => ['required', 'string', 'max:5000'],
        ]);

        $existing = LeadActivity::where('uuid', $validated['offline_uuid'])->first();
        if ($existing) {
            abort_unless((int) $existing->lead_id === (int) $lead->id, 409);

            return ApiResponse::success($this->activityPayload($existing));
        }

        $activity = $pipeline->activity($lead, $user, $validated['type'], $validated['notes'], [], $validated['offline_uuid']);

        return ApiResponse::success($this->activityPayload($activity), 201);
    }

    public function convert(Request $request, Lead $lead, LeadPipelineService $pipeline, AuditLogger $audit): JsonResponse
    {
        [$user, $salesman] = $this->salesman($request, 'leads:manage');
        abort_unless((int) $lead->assigned_salesman_id === (int) $salesman->id, 404);
        $customer = $pipeline->convert($lead, $user);
        $audit->record('lead.converted_mobile', $lead->fresh(), [], ['customer_id' => $customer->id]);

        return ApiResponse::success($this->payload($lead->fresh()->load(['convertedCustomer', 'territory'])));
    }

    private function salesman(Request $request, string $permission): array
    {
        abort_unless($request->user()->hasPermission($permission), 403);
        $user = $request->user()->load(['salesman', 'tenant']);
        abort_unless($user->salesman?->is_active, 403);

        return [$user, $user->salesman];
    }

    private function payload(Lead $lead): array
    {
        return [
            'id' => $lead->uuid,
            'name' => $lead->name,
            'contact_person' => $lead->contact_person,
            'phone' => $lead->phone,
            'email' => $lead->email,
            'address' => $lead->address,
            'source' => $lead->source,
            'stage' => $lead->stage,
            'priority' => $lead->priority,
            'estimated_value' => $lead->estimated_value !== null ? (float) $lead->estimated_value : null,
            'currency' => $lead->currency,
            'probability' => $lead->probability,
            'expected_close_date' => $lead->expected_close_date?->toDateString(),
            'lost_reason' => $lead->lost_reason,
            'notes' => $lead->notes,
            'territory_id' => $lead->territory?->uuid,
            'territory_name' => $lead->territory?->name,
            'converted_customer_id' => $lead->convertedCustomer?->uuid,
            'converted_customer_name' => $lead->convertedCustomer?->name,
            'last_activity_at' => $lead->last_activity_at?->toISOString(),
            'converted_at' => $lead->converted_at?->toISOString(),
            'updated_at' => $lead->updated_at?->toISOString(),
        ];
    }

    private function activityPayload(LeadActivity $activity): array
    {
        return [
            'id' => $activity->uuid,
            'type' => $activity->type,
            'notes' => $activity->notes,
            'occurred_at' => $activity->occurred_at?->toISOString(),
            'user_name' => $activity->user?->name,
        ];
    }
}
