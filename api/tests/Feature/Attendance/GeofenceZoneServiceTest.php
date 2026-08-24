<?php

declare(strict_types=1);

namespace Tests\Feature\Attendance;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\Site;
use App\Modules\Attendance\Infrastructure\Services\GeofenceZoneService;
use App\Modules\SmartAttendance\Domain\Exceptions\OutsideGeofenceException;
use Illuminate\Support\Facades\Hash;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * ADR-0016 Phase 2 (issue #5353) — chemin d'usage UNIQUE de la géofence.
 *
 * GeofenceZoneService est l'unique consommateur direct d'AttendanceGeofenceService ;
 * ce test verrouille les trois comportements du chemin unifié :
 *   - evaluateZone() : évaluation pure, informative (jamais d'exception)
 *   - assertInsideZone() : politique bloquante unique (OutsideGeofenceException)
 *   - resolveSiteId() : résolution du site assigné par distance
 */
class GeofenceZoneServiceTest extends TestCase
{
    use RefreshTenantDatabase;

    private const CENTER_LAT = 36.7538;

    private const CENTER_LNG = 3.0588;

    private Company $company;

    private Employee $employee;

    private GeofenceZoneService $zoneService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::query()->create([
            'name' => 'Geofence Corp',
            'slug' => 'geofence-corp',
            'sector' => 'restaurant',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => 'geo@corp.test',
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
            'plan_id' => 1,
            'subscription_start' => '2026-01-01',
            'subscription_end' => '2027-01-01',
            'language' => 'fr',
            'currency' => 'DZD',
            'timezone' => 'UTC',
            'metadata' => [
                'attendance_geofence' => [
                    'lat' => self::CENTER_LAT,
                    'lng' => self::CENTER_LNG,
                    'radius_meters' => 100,
                ],
            ],
        ]);

        $this->employee = new Employee([
            'first_name' => 'Karim',
            'last_name' => 'Bensalem',
            'email' => 'karim@corp.test',
            'phone' => '+213555000000',
        ]);
        $this->employee->forceFill(['password_hash' => Hash::make('password')])->save();
        $this->employee->forceFill([
            'company_id' => $this->company->id,
            'role' => 'employee',
            'status' => 'active',
        ])->save();

        $this->zoneService = app(GeofenceZoneService::class);
        $this->app->instance('current_company', $this->company);
    }

    public function test_evaluate_zone_returns_geo_payload_without_throwing(): void
    {
        // À l'intérieur du rayon (≈ 10 m du centre)
        $geo = $this->zoneService->evaluateZone(
            $this->company,
            $this->employee,
            self::CENTER_LAT + 0.0001,
            self::CENTER_LNG,
        );

        $this->assertTrue($geo['configured']);
        $this->assertTrue($geo['inside']);
        $this->assertSame('company_metadata', $geo['source']);
        $this->assertLessThanOrEqual(100, $geo['distance_meters']);
    }

    public function test_evaluate_zone_is_informational_when_outside(): void
    {
        // Hors rayon (~ 5 km du centre) : l'évaluation pure ne jette PAS.
        $geo = $this->zoneService->evaluateZone(
            $this->company,
            $this->employee,
            self::CENTER_LAT + 0.05,
            self::CENTER_LNG,
        );

        $this->assertTrue($geo['configured']);
        $this->assertFalse($geo['inside']);
        $this->assertGreaterThan(100, $geo['distance_meters']);
    }

    public function test_evaluate_zone_not_configured_when_no_geofence(): void
    {
        $this->company->update(['metadata' => []]);

        $geo = $this->zoneService->evaluateZone(
            $this->company,
            $this->employee,
            self::CENTER_LAT,
            self::CENTER_LNG,
        );

        $this->assertFalse($geo['configured']);
        $this->assertNull($geo['inside']);
    }

    public function test_assert_inside_zone_throws_when_outside(): void
    {
        try {
            $this->zoneService->assertInsideZone(
                $this->company,
                $this->employee,
                self::CENTER_LAT + 0.05,
                self::CENTER_LNG,
            );
            $this->fail('OutsideGeofenceException attendue hors zone.');
        } catch (OutsideGeofenceException $e) {
            $this->assertStringContainsString('outside the geofence', $e->getMessage());
            $this->assertGreaterThan(100.0, $this->extractDistance($e));
        }
    }

    public function test_assert_inside_zone_passes_inside(): void
    {
        $geo = $this->zoneService->assertInsideZone(
            $this->company,
            $this->employee,
            self::CENTER_LAT + 0.0001,
            self::CENTER_LNG,
        );

        $this->assertTrue($geo['inside']);
    }

    public function test_assert_inside_zone_passes_when_not_configured(): void
    {
        $this->company->update(['metadata' => []]);

        $geo = $this->zoneService->assertInsideZone(
            $this->company,
            $this->employee,
            self::CENTER_LAT,
            self::CENTER_LNG,
        );

        $this->assertFalse($geo['configured']);
        $this->assertNull($geo['inside']);
    }

    public function test_resolve_site_id_returns_site_within_radius(): void
    {
        $site = $this->makeSiteWithGps(self::CENTER_LAT, self::CENTER_LNG, 100);
        $this->employee->update(['site_id' => $site->id]);

        $siteId = $this->zoneService->resolveSiteId(
            $this->employee,
            $this->company,
            self::CENTER_LAT + 0.0001,
            self::CENTER_LNG,
        );

        $this->assertSame($site->id, $siteId);
    }

    public function test_resolve_site_id_returns_null_outside_radius(): void
    {
        $site = $this->makeSiteWithGps(self::CENTER_LAT, self::CENTER_LNG, 50);
        $this->employee->update(['site_id' => $site->id]);

        $siteId = $this->zoneService->resolveSiteId(
            $this->employee,
            $this->company,
            self::CENTER_LAT + 0.01,
            self::CENTER_LNG,
        );

        $this->assertNull($siteId);
    }

    public function test_resolve_site_id_returns_null_without_site(): void
    {
        $siteId = $this->zoneService->resolveSiteId(
            $this->employee,
            $this->company,
            self::CENTER_LAT,
            self::CENTER_LNG,
        );

        $this->assertNull($siteId);
    }

    /**
     * @return float distance extraite du message d'exception (débogage).
     */
    private function extractDistance(OutsideGeofenceException $e): float
    {
        preg_match('/distance: ([\d.]+)m/', $e->getMessage(), $m);

        return isset($m[1]) ? (float) $m[1] : 0.0;
    }

    private function makeSiteWithGps(float $lat, float $lng, int $radius): Site
    {
        /** @var Site $site */
        $site = Site::query()->create([
            'company_id' => $this->company->id,
            'name' => 'Site Alger Centre',
            'gps_lat' => $lat,
            'gps_lng' => $lng,
            'gps_radius_m' => $radius,
        ]);

        return $site;
    }
}
