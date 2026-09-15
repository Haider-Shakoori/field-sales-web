<?php

namespace App\Http\Requests;

use App\Models\Salesman;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSalesmanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Salesman::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $tenantId = TenantContext::currentId();

        return [
            'user_id' => ['nullable', Rule::exists('users', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId))],
            'employee_code' => ['nullable', 'string', 'max:50', Rule::unique('salesmen', 'employee_code')->where('tenant_id', $tenantId)],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'hire_date' => ['nullable', 'date', 'before_or_equal:today'],
            'designation' => ['nullable', 'string', 'max:100'],
        ];
    }
}
