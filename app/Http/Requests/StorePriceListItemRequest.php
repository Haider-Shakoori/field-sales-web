<?php

namespace App\Http\Requests;

use App\Models\PriceList;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePriceListItemRequest extends FormRequest
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
            'product_id' => [
                'required',
                'integer',
                Rule::exists('products', 'id')->where('tenant_id', $tenantId),
            ],
            'min_quantity' => [
                'required',
                'numeric',
                'gt:0',
                Rule::unique('price_list_items', 'min_quantity')
                    ->where('price_list_id', $priceList->id)
                    ->where('product_id', $this->integer('product_id')),
            ],
            'price' => ['required', 'numeric', 'min:0'],
        ];
    }
}
