<?php

namespace App\Services;

use App\Models\Customer;

class GeofenceService
{
    public function evaluate(Customer $customer, float $latitude, float $longitude): array
    {
        if ($customer->latitude === null || $customer->longitude === null) {
            return ['distance_meters' => null, 'within_geofence' => null];
        }

        $distance = $this->distanceMeters(
            (float) $customer->latitude,
            (float) $customer->longitude,
            $latitude,
            $longitude,
        );

        return [
            'distance_meters' => round($distance, 2),
            'within_geofence' => $distance <= (int) $customer->geofence_radius_meters,
        ];
    }

    public function distanceMeters(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $radius = 6371000.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return $radius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
