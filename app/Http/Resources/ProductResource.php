<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'sku' => $this->sku,
            'name' => $this->name,
            'category' => $this->category,
            'unit' => $this->unit,
            'price' => (float) $this->price,
            'is_active' => $this->is_active,
            'price_lists' => $this->whenLoaded('priceListItems', function () {
                return $this->priceListItems->map(fn ($item) => [
                    'id' => $item->priceList->id,
                    'name' => $item->priceList->name,
                    'price' => (float) $item->price,
                ]);
            }),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
