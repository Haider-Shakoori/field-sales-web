<?php

namespace App\Http\Requests;

use App\Models\PriceList;
use App\Models\PriceListItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePriceListItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        $priceList = $this->route('priceList');
        $item = $this->route('priceListItem');

        return $priceList instanceof PriceList
            && $item instanceof PriceListItem
            && (int) $item->price_list_id === (int) $priceList->id
            && ($this->user()?->can('update', $priceList) ?? false);
    }

    public function rules(): array
    {
        /** @var PriceList $priceList */
        $priceList = $this->route('priceList');
        /** @var PriceListItem $item */
        $item = $this->route('priceListItem');
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
                    ->where('product_id', $this->integer('product_id'))
                    ->ignore($item->id),
            ],
            'price' => ['required', 'numeric', 'min:0'],
        ];
    }
}
