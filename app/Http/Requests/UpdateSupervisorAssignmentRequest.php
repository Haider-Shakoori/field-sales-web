<?php

namespace App\Http\Requests;

use App\Models\SupervisorAssignment;
use App\Models\Territory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateSupervisorAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $assignment = $this->route('assignment');

        return $assignment instanceof SupervisorAssignment
            && ($this->user()?->can('update', $assignment) ?? false);
    }

    public function rules(): array
    {
        $tenantId = $this->user()->tenant_id;

        return [
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')->where('tenant_id', $tenantId)],
            'territory_id' => ['nullable', 'integer', Rule::exists('territories', 'id')->where('tenant_id', $tenantId)],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $branchId = $this->integer('branch_id');
                $territoryId = $this->integer('territory_id');
                $territory = $territoryId ? Territory::find($territoryId) : null;

                if ($territory && $branchId && $territory->branch_id && (int) $territory->branch_id !== $branchId) {
                    $validator->errors()->add('territory_id', 'The territory must belong to the selected branch.');
                }
            },
        ];
    }
}
