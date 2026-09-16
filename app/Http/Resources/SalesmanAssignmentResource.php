<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalesmanAssignmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'salesman_id' => $this->salesman_id,
            'salesman' => $this->whenLoaded('salesman', fn () => [
                'id' => $this->salesman->id,
                'name' => trim($this->salesman->first_name.' '.$this->salesman->last_name),
                'employee_code' => $this->salesman->employee_code,
            ]),
            'branch_id' => $this->branch_id,
            'branch' => $this->whenLoaded('branch', fn () => [
                'id' => $this->branch->id,
                'name' => $this->branch->name,
            ]),
            'territory_id' => $this->territory_id,
            'territory' => $this->whenLoaded('territory', fn () => [
                'id' => $this->territory->id,
                'name' => $this->territory->name,
            ]),
            'route_id' => $this->route_id,
            'route' => $this->whenLoaded('route', fn () => [
                'id' => $this->route->id,
                'name' => $this->route->name,
            ]),
            'supervisor_id' => $this->supervisor_id,
            'supervisor' => $this->whenLoaded('supervisor', fn () => [
                'id' => $this->supervisor->id,
                'name' => trim($this->supervisor->first_name.' '.$this->supervisor->last_name),
                'employee_code' => $this->supervisor->employee_code,
            ]),
            'effective_from' => $this->effective_from?->toDateString(),
            'effective_to' => $this->effective_to?->toDateString(),
            'is_currently_active' => $this->isCurrentlyActive(),
            'created_by' => $this->whenLoaded('createdBy', fn () => $this->createdBy->id),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
