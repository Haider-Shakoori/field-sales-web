<?php

namespace App\Services;

use App\Models\Territory;
use Illuminate\Support\Collection;

class TerritoryLocator
{
    public function locate(float $latitude, float $longitude, ?int $preferredBranchId = null): ?Territory
    {
        $query = Territory::query()
            ->active()
            ->whereNotNull('polygon')
            ->orderBy('id');

        /** @var Collection<int, Territory> $territories */
        $territories = $query->get();

        if ($preferredBranchId !== null) {
            $preferred = $territories->filter(
                fn (Territory $territory): bool => $territory->branch_id === null
                    || (int) $territory->branch_id === $preferredBranchId
            );

            $match = $this->firstContaining($preferred, $latitude, $longitude);

            if ($match) {
                return $match;
            }
        }

        return $this->firstContaining($territories, $latitude, $longitude);
    }

    public function contains(Territory $territory, float $latitude, float $longitude): bool
    {
        return $this->geometryContains($territory->polygon, $latitude, $longitude);
    }

    private function firstContaining(
        Collection $territories,
        float $latitude,
        float $longitude,
    ): ?Territory {
        foreach ($territories as $territory) {
            if ($this->contains($territory, $latitude, $longitude)) {
                return $territory;
            }
        }

        return null;
    }

    private function geometryContains(mixed $geometry, float $latitude, float $longitude): bool
    {
        if (! is_array($geometry) || $geometry === []) {
            return false;
        }

        // Legacy FieldPulse polygons used [[lat, lng], ...]. Normalize those
        // so older territories continue to participate in auto-detection.
        if (array_is_list($geometry) && isset($geometry[0]) && is_array($geometry[0])) {
            $ring = collect($geometry)
                ->filter(fn ($point): bool => is_array($point) && count($point) >= 2)
                ->map(fn ($point): array => [(float) $point[1], (float) $point[0]])
                ->values()
                ->all();

            return $this->polygonContains([$ring], $latitude, $longitude);
        }

        $type = $geometry['type'] ?? null;

        if ($type === 'Feature') {
            return $this->geometryContains(
                $geometry['geometry'] ?? null,
                $latitude,
                $longitude,
            );
        }

        if ($type === 'FeatureCollection') {
            return collect($geometry['features'] ?? [])
                ->contains(
                    fn ($feature): bool => is_array($feature)
                        && $this->geometryContains(
                            $feature,
                            $latitude,
                            $longitude,
                        )
                );
        }

        $coordinates = $geometry['coordinates'] ?? null;

        if (! is_array($coordinates)) {
            return false;
        }

        return match ($type) {
            'Polygon' => $this->polygonContains($coordinates, $latitude, $longitude),
            'MultiPolygon' => collect($coordinates)->contains(
                fn ($polygon): bool => is_array($polygon)
                    && $this->polygonContains($polygon, $latitude, $longitude)
            ),
            default => false,
        };
    }

    private function polygonContains(array $rings, float $latitude, float $longitude): bool
    {
        $outer = $rings[0] ?? null;

        if (! is_array($outer) || ! $this->ringContains($outer, $latitude, $longitude)) {
            return false;
        }

        foreach (array_slice($rings, 1) as $hole) {
            if (is_array($hole) && $this->ringContains($hole, $latitude, $longitude)) {
                return false;
            }
        }

        return true;
    }

    private function ringContains(array $ring, float $latitude, float $longitude): bool
    {
        $points = collect($ring)
            ->filter(fn ($point): bool => is_array($point) && count($point) >= 2)
            ->map(fn ($point): array => [(float) $point[0], (float) $point[1]])
            ->values()
            ->all();

        if (count($points) < 3) {
            return false;
        }

        $inside = false;
        $count = count($points);

        for ($i = 0, $j = $count - 1; $i < $count; $j = $i++) {
            [$xi, $yi] = $points[$i];
            [$xj, $yj] = $points[$j];

            if ($this->pointOnSegment($longitude, $latitude, $xi, $yi, $xj, $yj)) {
                return true;
            }

            $crosses = (($yi > $latitude) !== ($yj > $latitude))
                && ($longitude < (($xj - $xi) * ($latitude - $yi) / (($yj - $yi) ?: 1e-12)) + $xi);

            if ($crosses) {
                $inside = ! $inside;
            }
        }

        return $inside;
    }

    private function pointOnSegment(
        float $x,
        float $y,
        float $x1,
        float $y1,
        float $x2,
        float $y2,
    ): bool {
        $cross = ($x - $x1) * ($y2 - $y1) - ($y - $y1) * ($x2 - $x1);

        if (abs($cross) > 1e-9) {
            return false;
        }

        $lengthSquared = ($x2 - $x1) ** 2 + ($y2 - $y1) ** 2;

        if ($lengthSquared <= 1e-18) {
            return (($x - $x1) ** 2 + ($y - $y1) ** 2) <= 1e-18;
        }

        $dot = ($x - $x1) * ($x2 - $x1) + ($y - $y1) * ($y2 - $y1);

        if ($dot < -1e-9) {
            return false;
        }

        return $dot <= $lengthSquared + 1e-9;
    }
}
