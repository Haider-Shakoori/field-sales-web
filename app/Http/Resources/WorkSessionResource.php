<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkSessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $odometerDistance = null;

        if (
            $this->odometer_start_km !== null
            && $this->odometer_end_km !== null
            && (float) $this->odometer_end_km >= (float) $this->odometer_start_km
        ) {
            $odometerDistance = round(
                (float) $this->odometer_end_km - (float) $this->odometer_start_km,
                2,
            );
        }

        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'offline_uuid' => $this->uuid,
            'user_id' => $this->user?->uuid,
            'salesman_id' => $this->salesman_id,
            'device_id' => $this->device?->uuid,
            'date' => $this->date?->format('Y-m-d'),
            'status' => $this->status,
            'started_at' => $this->start_time?->toISOString(),
            'ended_at' => $this->end_time?->toISOString(),
            'duration_minutes' => $this->duration_minutes,
            'duration_hours' => $this->duration_minutes === null
                ? null
                : round($this->duration_minutes / 60, 2),
            'start_location' => [
                'latitude' => $this->start_latitude,
                'longitude' => $this->start_longitude,
                'accuracy' => $this->start_accuracy,
            ],
            'end_location' => $this->end_time ? [
                'latitude' => $this->end_latitude,
                'longitude' => $this->end_longitude,
                'accuracy' => $this->end_accuracy,
            ] : null,
            'vehicle_reference' => $this->vehicle_reference,
            'odometer_start_km' => $this->odometer_start_km === null
                ? null
                : (float) $this->odometer_start_km,
            'odometer_end_km' => $this->odometer_end_km === null
                ? null
                : (float) $this->odometer_end_km,
            'odometer_distance_km' => $odometerDistance,
            'gps_distance_km' => $this->gps_distance_km === null
                ? null
                : (float) $this->gps_distance_km,
            'distance_calculated_at' => $this->distance_calculated_at?->toISOString(),
            'is_late_start' => (bool) $this->is_late_start,
            'is_early_finish' => (bool) $this->is_early_finish,
        ];
    }
}
