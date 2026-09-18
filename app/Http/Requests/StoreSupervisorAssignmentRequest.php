<?php

namespace App\Http\Requests;

use App\Models\Territory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreSupervisorAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('sales-team:manage') ?? false;
    }

    public function rules(): array
    {
        $tenantId = $this->user()->tenant_id;

        return [
            'supervisor_id' => ['required', 'integer', Rule::exists('supervisors', 'id')->where('tenant_id', $tenantId)],
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
