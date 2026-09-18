<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CurrentLocationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'salesman_id' => $this->salesman_id,
            'device_id' => $this->device_id,
            'latitude' => (float) $this->latitude,
            'longitude' => (float) $this->longitude,
            'accuracy' => $this->horizontal_accuracy !== null ? (float) $this->horizontal_accuracy : null,
            'altitude' => $this->altitude !== null ? (float) $this->altitude : null,
            'speed' => $this->speed !== null ? (float) $this->speed : null,
            'heading' => $this->heading !== null ? (float) $this->heading : null,
            'battery_level' => $this->battery_level,
            'is_charging' => (bool) $this->is_charging,
            'network_status' => $this->network_status,
            'is_mock_location' => (bool) $this->is_mock_location,
            'provider' => $this->provider,
            'recorded_at' => $this->recorded_at?->toIso8601String(),
            'received_at' => $this->received_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
