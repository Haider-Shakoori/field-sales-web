<?php

namespace App\Http\Requests;

use App\Models\PriceList;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePriceListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', PriceList::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $tenantId = TenantContext::currentId();

        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('price_lists', 'name')->where('tenant_id', $tenantId)],
            'is_default' => ['boolean'],
            'is_active' => ['boolean'],
        ];
    }
}
