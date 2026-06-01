<?php

namespace Tests\Unit;

use App\Models\Company;
use App\Models\Employee;
use App\Services\AttendanceGeofenceService;
use PHPUnit\Framework\TestCase;

class AttendanceGeofenceServiceTest extends TestCase
{
    public function test_distance_meters_uses_haversine_distance(): void
    {
        $service = new AttendanceGeofenceService;

        $distance = $service->distanceMeters(36.7538, 3.0588, 36.7548, 3.0588);

        $this->assertGreaterThan(100, $distance);
        $this->assertLessThan(120, $distance);
    }

    public function test_company_metadata_geofence_is_soft_evaluated(): void
    {
        $service = new AttendanceGeofenceService;
        $company = new Company([
            'metadata' => [
                'attendance_geofence' => [
                    'lat' => 36.7538,
                    'lng' => 3.0588,
                    'radius_meters' => 100,
                ],
            ],
        ]);
        $employee = new Employee(['company_id' => 'company-a']);

        $inside = $service->evaluate($company, $employee, 36.7539, 3.0588);
        $outside = $service->evaluate($company, $employee, 36.7600, 3.0588);

        $this->assertTrue($inside['configured']);
        $this->assertTrue($inside['inside']);
        $this->assertFalse($outside['inside']);
        $this->assertSame('company_metadata', $outside['source']);
    }
}
