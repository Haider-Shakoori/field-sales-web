<?php

namespace App\Http\Requests;

use App\Models\Supervisor;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSupervisorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Supervisor::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $tenantId = TenantContext::currentId();

        return [
            'user_id' => ['required', Rule::exists('users', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId))],
            'employee_code' => ['nullable', 'string', 'max:50', Rule::unique('supervisors', 'employee_code')->where('tenant_id', $tenantId)],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:50'],
        ];
    }
}
