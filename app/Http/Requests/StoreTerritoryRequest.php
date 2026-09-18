<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTerritoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('customers:manage') ?? false;
    }

    public function rules(): array
    {
        $tenantId = $this->user()->tenant_id;

        return [
            'branch_id' => [
                'nullable',
                'integer',
                Rule::exists('branches', 'id')->where('tenant_id', $tenantId),
            ],
            'code' => [
                'required',
                'string',
                'max:60',
                Rule::unique('territories', 'code')->where('tenant_id', $tenantId),
            ],
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
            'polygon' => ['nullable', 'json'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
