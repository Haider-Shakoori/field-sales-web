<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('roles:manage') ?? false;
    }

    public function rules(): array
    {
        $tenantId = $this->user()->tenant_id;

        return [
            'name' => ['required', 'string', 'max:120'],
            'slug' => [
                'required',
                'string',
                'max:80',
                'alpha_dash:ascii',
                Rule::unique('roles', 'slug')->where('tenant_id', $tenantId),
            ],
            'permission_ids' => ['array'],
            'permission_ids.*' => ['integer', 'exists:permissions,id'],
        ];
    }
}
