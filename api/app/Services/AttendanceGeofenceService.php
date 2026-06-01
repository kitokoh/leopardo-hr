<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Employee;
use App\Models\Site;

class AttendanceGeofenceService
{
    /**
     * @return array<string, mixed>
     */
    public function evaluate(Company $company, Employee $employee, ?float $lat, ?float $lng): array
    {
        $target = $this->resolveTarget($company, $employee);

        if ($target === null) {
            return [
                'configured' => false,
                'inside' => null,
                'distance_meters' => null,
                'radius_meters' => null,
                'source' => null,
            ];
        }

        if ($lat === null || $lng === null) {
            return [
                ...$target,
                'configured' => true,
                'inside' => null,
                'distance_meters' => null,
            ];
        }

        $distance = $this->distanceMeters(
            $lat,
            $lng,
            (float) $target['lat'],
            (float) $target['lng'],
        );

        return [
            'configured' => true,
            'inside' => $distance <= (float) $target['radius_meters'],
            'distance_meters' => (int) round($distance),
            'radius_meters' => (int) $target['radius_meters'],
            'source' => $target['source'],
        ];
    }

    public function distanceMeters(float $fromLat, float $fromLng, float $toLat, float $toLng): float
    {
        $earthRadiusMeters = 6371000;
        $latDelta = deg2rad($toLat - $fromLat);
        $lngDelta = deg2rad($toLng - $fromLng);

        $a = sin($latDelta / 2) ** 2
            + cos(deg2rad($fromLat)) * cos(deg2rad($toLat)) * sin($lngDelta / 2) ** 2;

        return $earthRadiusMeters * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /**
     * @return array{lat: float, lng: float, radius_meters: int, source: string}|null
     */
    private function resolveTarget(Company $company, Employee $employee): ?array
    {
        $siteId = (int) ($employee->site_id ?? 0);
        if ($siteId > 0) {
            $site = Site::query()
                ->where('company_id', $employee->company_id)
                ->find($siteId);

            if ($site && $site->gps_lat !== null && $site->gps_lng !== null) {
                return [
                    'lat' => (float) $site->gps_lat,
                    'lng' => (float) $site->gps_lng,
                    'radius_meters' => max(10, (int) $site->gps_radius_m),
                    'source' => 'site',
                ];
            }
        }

        $metadata = $company->metadata ?? [];
        $geofence = is_array($metadata) ? ($metadata['attendance_geofence'] ?? null) : null;

        if (! is_array($geofence) || ! isset($geofence['lat'], $geofence['lng'], $geofence['radius_meters'])) {
            return null;
        }

        $radius = (int) $geofence['radius_meters'];
        if ($radius <= 0) {
            return null;
        }

        return [
            'lat' => (float) $geofence['lat'],
            'lng' => (float) $geofence['lng'],
            'radius_meters' => max(10, $radius),
            'source' => 'company_metadata',
        ];
    }
}
