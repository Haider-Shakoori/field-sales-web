<?php

namespace App\Http\Requests;

use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePriceListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('price_list')) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $tenantId = TenantContext::currentId();

        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('price_lists', 'name')->where('tenant_id', $tenantId)->ignore($this->route('price_list'))],
            'is_default' => ['boolean'],
            'is_active' => ['boolean'],
        ];
    }
}
