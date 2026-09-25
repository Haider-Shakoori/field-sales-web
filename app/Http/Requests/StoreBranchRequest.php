<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBranchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('branches:manage') ?? false;
    }

    public function rules(): array
    {
        $tenantId = $this->user()->tenant_id;

        return [
            'name' => ['required', 'string', 'max:120'],
            'code' => [
                'required',
                'string',
                'max:40',
                Rule::unique('branches', 'code')->where('tenant_id', $tenantId),
            ],
            'geofence_polygon' => ['nullable', 'json'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
