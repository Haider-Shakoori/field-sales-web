<?php

namespace App\Http\Requests;

use App\Models\Territory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTerritoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $territory = $this->route('territory');

        return $territory instanceof Territory
            && ($this->user()?->can('update', $territory) ?? false);
    }

    public function rules(): array
    {
        /** @var Territory $territory */
        $territory = $this->route('territory');
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
                Rule::unique('territories', 'code')
                    ->where('tenant_id', $tenantId)
                    ->ignore($territory->id),
            ],
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
            'polygon' => ['nullable', 'json'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
