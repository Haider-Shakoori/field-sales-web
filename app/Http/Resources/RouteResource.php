<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RouteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'territory_id' => $this->territory_id,
            'territory' => $this->whenLoaded('territory', fn () => [
                'id' => $this->territory->id,
                'name' => $this->territory->name,
            ]),
            'branch_id' => $this->whenLoaded('territory', fn () => $this->territory->branch_id),
            'branch' => $this->whenLoaded('territory', fn () => $this->territory->branch?->name),
            'weekday' => $this->weekday,
            'weekday_name' => $this->weekday !== null
                ? ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'][$this->weekday]
                : 'Any day',
            'is_active' => $this->is_active,
            'customer_count' => $this->whenCounted('customers'),
            'salesman_id' => $this->whenLoaded('salesmanAssignments', fn () => $this->salesmanAssignments->first()?->salesman_id),
            'salesman' => $this->whenLoaded('salesmanAssignments', fn () => $this->salesmanAssignments->first()?->salesman ? [
                'id' => $this->salesmanAssignments->first()->salesman->id,
                'name' => trim($this->salesmanAssignments->first()->salesman->first_name.' '.$this->salesmanAssignments->first()->salesman->last_name),
            ] : null),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
