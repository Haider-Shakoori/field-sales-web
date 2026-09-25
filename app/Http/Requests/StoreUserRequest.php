<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('users:manage') ?? false;
    }

    public function rules(): array
    {
        $tenantId = $this->user()->tenant_id;

        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => [
                'required',
                'email',
                'max:191',
                Rule::unique('users', 'email')->where('tenant_id', $tenantId),
            ],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'branch_id' => [
                'nullable',
                'integer',
                Rule::exists('branches', 'id')->where('tenant_id', $tenantId),
            ],
            'role_id' => [
                'required',
                'integer',
                Rule::exists('roles', 'id')->where('tenant_id', $tenantId),
            ],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
