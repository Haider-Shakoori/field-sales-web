<?php

namespace App\Http\Requests;

use App\Models\PriceList;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePriceListRequest extends FormRequest
{
    public function authorize(): bool
    {
        $priceList = $this->route('priceList');

        return $priceList instanceof PriceList
            && ($this->user()?->can('update', $priceList) ?? false);
    }

    public function rules(): array
    {
        /** @var PriceList $priceList */
        $priceList = $this->route('priceList');
        $tenantId = $this->user()->tenant_id;

        return [
            'code' => [
                'required',
                'string',
                'max:60',
                Rule::unique('price_lists', 'code')
                    ->where('tenant_id', $tenantId)
                    ->ignore($priceList->id),
            ],
            'name' => ['required', 'string', 'max:180'],
            'currency' => ['required', 'string', 'size:3'],
            'effective_from' => ['nullable', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
