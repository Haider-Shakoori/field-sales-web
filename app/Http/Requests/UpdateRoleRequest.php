<?php

namespace App\Http\Requests;

use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $role = $this->route('role');

        return $role instanceof Role
            && ($this->user()?->can('update', $role) ?? false);
    }

    public function rules(): array
    {
        /** @var Role $role */
        $role = $this->route('role');
        $tenantId = $this->user()->tenant_id;

        return [
            'name' => ['required', 'string', 'max:120'],
            'slug' => [
                'required',
                'string',
                'max:80',
                'alpha_dash:ascii',
                Rule::unique('roles', 'slug')
                    ->where('tenant_id', $tenantId)
                    ->ignore($role->id),
            ],
            'permission_ids' => ['array'],
            'permission_ids.*' => ['integer', 'exists:permissions,id'],
        ];
    }
}
