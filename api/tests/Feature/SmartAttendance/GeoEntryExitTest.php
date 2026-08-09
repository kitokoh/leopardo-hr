<?php

declare(strict_types=1);

namespace Tests\Feature\SmartAttendance;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Planning\Domain\Models\Schedule;
use App\Modules\SmartAttendance\Domain\Models\GeoAttendanceSession;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;

use Tests\TestCase;

/**
 * Tests Feature — Entrée/Sortie GPS (geo-events endpoint)
 *
 * Endpoint : POST /api/v1/smart-attendance/geo-events
 */
class GeoEntryExitTest extends TestCase
{
    
    use RefreshTenantDatabase;

    // Coordonnées géofence de référence (Alger)
    private const GEO_LAT  = 36.7538;
    private const GEO_LNG  = 3.0588;
    private const GEO_RADIUS = 200;

    // Point dans la zone
    private const LAT_INSIDE = 36.7539;
    private const LNG_INSIDE = 3.0589;

    // Point hors zone (Paris)
    private const LAT_OUTSIDE = 48.8566;
    private const LNG_OUTSIDE = 2.3522;

    private Company $company;
    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::query()->create([
            'name'         => 'TestCorp Geo',
            'slug'         => 'testcorp-geo',
            'sector'       => 'tech',
            'country'      => 'DZ',
            'city'         => 'Alger',
            'email'        => 'geo@testcorp.test',
            'schema_name'  => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status'       => 'active',
            'timezone'     => 'Africa/Algiers',
            'metadata'     => [
                'attendance_geofence' => [
                    'lat'           => self::GEO_LAT,
                    'lng'           => self::GEO_LNG,
                    'radius_meters' => self::GEO_RADIUS,
                ],
            ],
        ]);

        $schedule = Schedule::query()->create([
            'company_id'                => $this->company->id,
            'name'                      => 'Standard',
            'start_time'                => '08:00:00',
            'end_time'                  => '17:00:00',
            'late_tolerance_minutes'    => 15,
            'overtime_threshold_daily'  => 8.0,
            'is_default'                => true,
        ]);

        $this->employee = Employee::query()->create([
            'company_id'    => $this->company->id,
            'schedule_id'   => $schedule->id,
            'email'         => 'emp@testcorp.test',
            'password_hash' => Hash::make('password'),
            'role'          => 'employee',
            'status'        => 'active',
        ]);
    }

    // ── Tests ────────────────────────────────────────────────────────────────

    /**
     * Un événement zone_enter dans la zone crée une GeoAttendanceSession
     * avec status=detected.
     */
    public function test_employee_zone_enter_creates_geo_session(): void
    {
        Sanctum::actingAs($this->employee);

        $response = $this->postJson('/api/v1/smart-attendance/geo-events', [
            'event_type'      => 'zone_enter',
            'latitude'        => self::LAT_INSIDE,
            'longitude'       => self::LNG_INSIDE,
            'accuracy_meters' => 10,
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.status', 'detected');
        $response->assertJsonStructure([
            'message',
            'data' => ['session_id', 'status', 'started_at', 'ended_at', 'duration_seconds'],
        ]);

        $this->assertDatabaseHas('geo_attendance_sessions', [
            'employee_id' => $this->employee->id,
            'company_id'  => $this->company->id,
            'status'      => GeoAttendanceSession::STATUS_DETECTED,
        ]);
    }

    /**
     * Un événement zone_exit ferme la session ouverte et calcule
     * duration_seconds > 0.
     */
    public function test_employee_zone_exit_closes_session(): void
    {
        // Créer une session ouverte
        $session = GeoAttendanceSession::query()->create([
            'employee_id'   => $this->employee->id,
            'company_id'    => $this->company->id,
            'started_at'    => now()->subHour(),
            'check_in_lat'  => self::LAT_INSIDE,
            'check_in_lng'  => self::LNG_INSIDE,
            'status'        => GeoAttendanceSession::STATUS_DETECTED,
        ]);

        Sanctum::actingAs($this->employee);

        $response = $this->postJson('/api/v1/smart-attendance/geo-events', [
            'event_type'      => 'zone_exit',
            'latitude'        => self::LAT_INSIDE,
            'longitude'       => self::LNG_INSIDE,
            'accuracy_meters' => 12,
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.status', GeoAttendanceSession::STATUS_PENDING_VALIDATION);

        $data = $response->json('data');
        $this->assertNotNull($data['ended_at'], 'ended_at doit être défini après zone_exit');
        $this->assertGreaterThan(0, $data['duration_seconds'], 'duration_seconds doit être > 0');

        $session->refresh();
        $this->assertSame(GeoAttendanceSession::STATUS_PENDING_VALIDATION, $session->status);
        $this->assertNotNull($session->ended_at);
        $this->assertGreaterThan(0, $session->duration_seconds);
    }

    /**
     * Un événement zone_enter avec coordonnées hors géofence retourne 422
     * avec code OUTSIDE_GEOFENCE.
     */
    public function test_zone_enter_outside_geofence_returns_422(): void
    {
        Sanctum::actingAs($this->employee);

        $response = $this->postJson('/api/v1/smart-attendance/geo-events', [
            'event_type'      => 'zone_enter',
            'latitude'        => self::LAT_OUTSIDE,
            'longitude'       => self::LNG_OUTSIDE,
            'accuracy_meters' => 15,
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('code', 'OUTSIDE_GEOFENCE');

        // Aucune session ne doit être créée
        $this->assertDatabaseMissing('geo_attendance_sessions', [
            'employee_id' => $this->employee->id,
        ]);
    }

    /**
     * Un deuxième zone_enter alors qu'une session est déjà ouverte
     * retourne 409 avec code SESSION_ALREADY_OPEN.
     */
    public function test_cannot_open_two_sessions_simultaneously(): void
    {
        // Créer une session ouverte
        GeoAttendanceSession::query()->create([
            'employee_id'   => $this->employee->id,
            'company_id'    => $this->company->id,
            'started_at'    => now()->subMinutes(30),
            'check_in_lat'  => self::LAT_INSIDE,
            'check_in_lng'  => self::LNG_INSIDE,
            'status'        => GeoAttendanceSession::STATUS_DETECTED,
        ]);

        Sanctum::actingAs($this->employee);

        $response = $this->postJson('/api/v1/smart-attendance/geo-events', [
            'event_type' => 'zone_enter',
            'latitude'   => self::LAT_INSIDE,
            'longitude'  => self::LNG_INSIDE,
        ]);

        $response->assertStatus(409);
        $response->assertJsonPath('code', 'SESSION_ALREADY_OPEN');

        // Toujours une seule session
        $this->assertSame(1, GeoAttendanceSession::query()
            ->where('employee_id', $this->employee->id)
            ->count());
    }

    /**
     * Un appel sans authentification retourne 401.
     */
    public function test_geo_event_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/smart-attendance/geo-events', [
            'event_type' => 'zone_enter',
            'latitude'   => self::LAT_INSIDE,
            'longitude'  => self::LNG_INSIDE,
        ]);

        $response->assertStatus(401);
    }

    /**
     * Un zone_exit sans session ouverte est accepté silencieusement
     * → 200 avec data=null (no-op, tolérance offline/retry).
     */
    public function test_zone_exit_without_open_session_returns_200_noop(): void
    {
        Sanctum::actingAs($this->employee);

        $response = $this->postJson('/api/v1/smart-attendance/geo-events', [
            'event_type' => 'zone_exit',
            'latitude'   => self::LAT_INSIDE,
            'longitude'  => self::LNG_INSIDE,
        ]);

        // Le contrôleur retourne 200 avec data=null selon GeoAttendanceController::event()
        $response->assertStatus(200);
        $response->assertJsonPath('data', null);
    }
}

