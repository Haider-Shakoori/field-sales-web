<?php

namespace App\Http\Requests;

use App\Models\Salesman;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSalesmanRequest extends FormRequest
{
    public function authorize(): bool
    {
        $salesman = $this->route('salesman');

        return $salesman instanceof Salesman
            && ($this->user()?->can('update', $salesman) ?? false);
    }

    public function rules(): array
    {
        /** @var Salesman $salesman */
        $salesman = $this->route('salesman');
        $tenantId = $this->user()->tenant_id;

        return [
            'employee_code' => [
                'required',
                'string',
                'max:60',
                Rule::unique('salesmen', 'employee_code')
                    ->where('tenant_id', $tenantId)
                    ->ignore($salesman->id),
            ],
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['nullable', 'string', 'max:120'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
