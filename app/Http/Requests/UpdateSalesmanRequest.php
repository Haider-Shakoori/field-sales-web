<?php

namespace App\Http\Requests;

use App\Models\Salesman;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSalesmanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('salesman')) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $tenantId = TenantContext::currentId();
        /** @var Salesman $salesman */
        $salesman = $this->route('salesman');

        return [
            'employee_code' => ['nullable', 'string', 'max:50', Rule::unique('salesmen', 'employee_code')
                ->where('tenant_id', $tenantId)
                ->ignore($salesman->id)],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'hire_date' => ['nullable', 'date', 'before_or_equal:today'],
            'designation' => ['nullable', 'string', 'max:100'],
        ];
    }
}
