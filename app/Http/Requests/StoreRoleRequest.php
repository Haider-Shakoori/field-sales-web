<?php

namespace App\Http\Requests;

use App\Models\Role;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Role::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $tenantId = TenantContext::currentId();

        return [
            'name' => [
                'required',
                'string',
                'max:125',
                'regex:/^[a-z][a-z0-9_]*$/',
                Rule::unique('roles', 'name')->where(fn ($query) => $query->when(
                    $tenantId !== null,
                    fn ($q) => $q->where(fn ($inner) => $inner->where('tenant_id', $tenantId)->orWhereNull('tenant_id')),
                )),
            ],
            'permissions' => ['required', 'array'],
            'permissions.*' => ['integer', Rule::exists('permissions', 'id')->where(fn ($query) => $this->permissionQuery($query))],
        ];
    }

    protected function permissionQuery($query)
    {
        if ($this->user()?->isSuperAdmin()) {
            return $query;
        }

        return $query->whereNotIn('name', config('tenancy.platform_permissions', []));
    }
}
