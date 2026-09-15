<?php

namespace App\Http\Requests;

use App\Models\Supervisor;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSupervisorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('supervisor')) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $tenantId = TenantContext::currentId();
        /** @var Supervisor $supervisor */
        $supervisor = $this->route('supervisor');

        return [
            'employee_code' => ['nullable', 'string', 'max:50', Rule::unique('supervisors', 'employee_code')
                ->where('tenant_id', $tenantId)
                ->ignore($supervisor->id)],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:50'],
        ];
    }
}
