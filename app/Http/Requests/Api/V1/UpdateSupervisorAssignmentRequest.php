<?php

namespace App\Http\Requests\Api\V1;

use App\Models\SupervisorAssignment;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSupervisorAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $assignment = $this->route('assignment');

        return $this->user()?->can('update', $assignment) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $tenantId = $this->route('assignment')?->tenant_id;
        $assignmentId = $this->route('assignment')?->id;

        return [
            'supervisor_id' => ['sometimes', Rule::exists('supervisors', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)->where('is_active', true))],
            'branch_id' => ['sometimes', Rule::exists('branches', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId))],
            'territory_id' => ['sometimes', Rule::exists('territories', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)->where('is_active', true))],
            'effective_from' => ['sometimes', 'date'],
            'effective_to' => ['nullable', 'date'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $tenantId = $this->route('assignment')?->tenant_id;
            $assignmentId = $this->route('assignment')?->id;
            $supervisorId = $this->input('supervisor_id') ?? $this->route('assignment')?->supervisor_id;
            $branchId = $this->input('branch_id') ?? $this->route('assignment')?->branch_id;
            $territoryId = $this->input('territory_id') ?? $this->route('assignment')?->territory_id;
            $effectiveFrom = $this->input('effective_from') ?? $this->route('assignment')?->effective_from;
            $effectiveTo = $this->input('effective_to') ?? $this->route('assignment')?->effective_to;

            if ($supervisorId && $branchId && $territoryId) {
                $duplicate = SupervisorAssignment::where('tenant_id', $tenantId)
                    ->where('supervisor_id', $supervisorId)
                    ->where('branch_id', $branchId)
                    ->where('territory_id', $territoryId)
                    ->where('id', '!=', $assignmentId)
                    ->where(function ($q) use ($effectiveFrom) {
                        $q->whereNull('effective_to')
                            ->orWhere('effective_to', '>=', $effectiveFrom);
                    })
                    ->where('effective_from', '<=', $effectiveTo ?? '9999-12-31')
                    ->exists();

                if ($duplicate) {
                    $validator->errors()->add('supervisor_id', 'This supervisor already has an active assignment for the selected branch and territory during this period.');
                }
            }
        });
    }
}
