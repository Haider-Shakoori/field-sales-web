<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'offline_uuid' => $this->uuid,
            'code' => $this->code,
            'name' => $this->business_name,
            'business_name' => $this->business_name,
            'contact_person' => $this->contact_person,
            'phone' => $this->phone,
            'whatsapp' => $this->whatsapp,
            'email' => $this->email ?? null,
            'address' => $this->address,
            'province' => $this->province,
            'district' => $this->district,
            'latitude' => $this->latitude ? (float) $this->latitude : null,
            'longitude' => $this->longitude ? (float) $this->longitude : null,
            'geofence_radius' => $this->geofence_radius,
            'photo_url' => $this->photo_url,
            'branch_id' => $this->branch_id,
            'branch' => $this->whenLoaded('branch', fn () => [
                'id' => $this->branch->id,
                'name' => $this->branch->name,
            ]),
            'category_id' => $this->category_id,
            'category' => $this->whenLoaded('category', fn () => [
                'id' => $this->category->id,
                'name' => $this->category->name,
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
            'assigned_salesman_id' => $this->assigned_salesman_id,
            'assigned_salesman' => $this->whenLoaded('assignedSalesman', fn () => [
                'id' => $this->assignedSalesman->id,
                'name' => trim($this->assignedSalesman->first_name.' '.$this->assignedSalesman->last_name),
                'employee_code' => $this->assignedSalesman->employee_code,
            ]),
            'credit_limit' => $this->credit_limit ? (float) $this->credit_limit : 0,
            'outstanding_balance' => $this->outstanding_balance ? (float) $this->outstanding_balance : 0,
            'current_balance' => $this->outstanding_balance ? (float) $this->outstanding_balance : 0,
            // price_list_id deferred to Batch 5 - PriceList model not yet implemented
            // 'price_list_id' => $this->price_list_id,
            // 'price_list' => $this->whenLoaded('priceList', fn () => [
            //     'id' => $this->priceList->id,
            //     'name' => $this->priceList->name,
            // ]),
            'visit_frequency' => $this->visit_frequency,
            'is_active' => $this->is_active,
            'notes' => $this->notes,
            'created_by' => $this->whenLoaded('createdBy', fn () => $this->createdBy->id),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
