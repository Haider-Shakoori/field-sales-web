<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeviceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'device_uuid' => $this->device_uuid,
            'installation_uuid' => $this->installation_uuid,
            'device_model' => $this->device_model,
            'manufacturer' => $this->manufacturer,
            'android_version' => $this->android_version,
            'app_version' => $this->app_version,
            'status' => $this->isActive() ? 'active' : 'revoked',
            'registered_at' => $this->registered_at?->toIso8601String(),
            'last_heartbeat_at' => $this->last_seen_at?->toIso8601String(),
            'revoked_at' => $this->revoked_at?->toIso8601String(),
            'salesman' => $this->salesman?->loadMissing('user') ? [
                'id' => $this->salesman->uuid,
                'employee_code' => $this->salesman->employee_code,
                'name' => trim($this->salesman->first_name.' '.$this->salesman->last_name),
            ] : null,
        ];
    }
}
