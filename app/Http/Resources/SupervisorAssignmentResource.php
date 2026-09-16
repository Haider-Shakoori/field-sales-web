<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupervisorAssignmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'supervisor_id' => $this->supervisor_id,
            'supervisor' => $this->whenLoaded('supervisor', fn () => [
                'id' => $this->supervisor->id,
                'name' => trim($this->supervisor->first_name.' '.$this->supervisor->last_name),
                'employee_code' => $this->supervisor->employee_code,
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
            'effective_from' => $this->effective_from?->toDateString(),
            'effective_to' => $this->effective_to?->toDateString(),
            'is_currently_active' => $this->isCurrentlyActive(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
