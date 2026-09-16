<?php

namespace App\Http\Requests;

use App\Models\Branch;
use App\Models\Supervisor;
use App\Models\SupervisorAssignment;
use App\Models\Territory;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSupervisorAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('supervisor_assignment')) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $tenantId = TenantContext::currentId();

        return [
            'supervisor_id' => ['required', Rule::exists('supervisors', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)->where('is_active', true))],
            'branch_id' => ['required', Rule::exists('branches', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId))],
            'territory_id' => ['nullable', Rule::exists('territories', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)->where('is_active', true))],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $tenantId = TenantContext::currentId();
            $supervisorId = $this->input('supervisor_id');
            $branchId = $this->input('branch_id');
            $territoryId = $this->input('territory_id');
            $effectiveFrom = $this->input('effective_from');
            $effectiveTo = $this->input('effective_to');
            $currentId = $this->route('supervisor_assignment')->id ?? null;

            // Validate territory belongs to the selected branch
            if ($territoryId && $branchId) {
                $territory = Territory::where('tenant_id', $tenantId)
                    ->where('id', $territoryId)
                    ->where('branch_id', $branchId)
                    ->first();

                if (! $territory) {
                    $validator->errors()->add('territory_id', 'The selected territory does not belong to the selected branch.');
                }
            }

            // Prevent duplicate active assignment for same supervisor + branch + territory
            if ($supervisorId && $branchId && $territoryId) {
                $duplicate = SupervisorAssignment::where('tenant_id', $tenantId)
                    ->where('supervisor_id', $supervisorId)
                    ->where('branch_id', $branchId)
                    ->where('territory_id', $territoryId)
                    ->where('id', '!=', $currentId)
                    ->where(function ($query) use ($effectiveFrom) {
                        $query->whereNull('effective_to')
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
