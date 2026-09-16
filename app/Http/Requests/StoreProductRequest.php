<?php

namespace App\Http\Requests;

use App\Models\Product;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Product::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $tenantId = TenantContext::currentId();

        return [
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['required', 'string', 'max:100', Rule::unique('products', 'sku')->where('tenant_id', $tenantId)],
            'unit' => ['required', 'string', Rule::in(Product::UNITS)],
            'price' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'is_active' => ['boolean'],
            'category' => ['nullable', 'string', 'max:100'],
        ];
    }
}
