<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $target = $this->route('user');

        return $target instanceof User
            && ($this->user()?->can('update', $target) ?? false);
    }

    public function rules(): array
    {
        /** @var User $target */
        $target = $this->route('user');
        $tenantId = $this->user()->tenant_id;

        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => [
                'required',
                'email',
                'max:191',
                Rule::unique('users', 'email')
                    ->where('tenant_id', $tenantId)
                    ->ignore($target->id),
            ],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
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
