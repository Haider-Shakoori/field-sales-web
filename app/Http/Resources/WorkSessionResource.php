<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkSessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'offline_uuid' => $this->uuid,
            'user_id' => $this->user_id,
            'salesman_id' => $this->salesman_id,
            'device_id' => $this->device_id,
            'date' => $this->date?->toDateString(),
            'status' => $this->status,
            'started_at' => $this->start_time?->toIso8601String(),
            'ended_at' => $this->end_time?->toIso8601String(),
            'duration_minutes' => $this->duration_minutes,
            'duration_hours' => $this->duration_minutes !== null
                ? round($this->duration_minutes / 60, 2)
                : null,
            'is_late_start' => (bool) $this->is_late_start,
            'is_early_finish' => (bool) $this->is_early_finish,
            'start_location' => [
                'latitude' => (float) $this->start_latitude,
                'longitude' => (float) $this->start_longitude,
            ],
            'end_location' => $this->end_latitude !== null && $this->end_longitude !== null
                ? [
                    'latitude' => (float) $this->end_latitude,
                    'longitude' => (float) $this->end_longitude,
                ]
                : null,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
