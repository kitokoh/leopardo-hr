<?php

declare(strict_types=1);

namespace Tests\Feature\SmartAttendance;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\SmartAttendance\Domain\Models\GeoAttendanceSession;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #3887 — le module SmartAttendance (présence intelligente GPS) était
 * le seul module métier SANS AUCUN test.
 *
 * Couverture :
 *  - GeoAttendanceController::event — zone_enter (session créée), doublon
 *    (409), hors géofence (422), zone_exit (fermeture), exit sans session ;
 *  - GeoSessionController — mySessions (liste personnelle), RBAC index
 *    (ordinary → 403, manager rh → OK) ;
 *  - AttendanceModeController — config (mode par défaut / mode forcé),
 *    updatePreference (mode forcé entreprise → 403).
 */
class SmartAttendanceFlowTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    private Employee $employee;

    private Employee $managerRh;

    protected function setUp(): void
    {
        parent::setUp();

        // Le schéma MVP ne crée pas les tables SmartAttendance (elles sont
        // seulement droppées par le trait) — on les crée ici, alignées sur
        // les migrations tenant/2026_06_29_0002xx.
        $this->createSmartAttendanceSchema();

        $this->company = Company::factory()->create(['country' => 'DZ']);
        $this->employee = Employee::factory()->create([
            'company_id' => $this->company->id,
            'role' => 'employee',
            'status' => 'active',
        ]);
        $this->managerRh = Employee::factory()->create([
            'company_id' => $this->company->id,
            'role' => 'manager',
            'manager_role' => 'rh',
            'status' => 'active',
        ]);
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    private function createSmartAttendanceSchema(): void
    {
        DB::statement('DROP TABLE IF EXISTS employee_location_events');
        DB::statement('DROP TABLE IF EXISTS geo_attendance_sessions');
        DB::statement('DROP TABLE IF EXISTS attendance_mode_settings');
        DB::statement('DROP TABLE IF EXISTS employee_attendance_preferences');

        DB::statement(<<<'SQL'
            CREATE TABLE geo_attendance_sessions (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                employee_id INT UNSIGNED NOT NULL,
                company_id CHAR(36) NOT NULL,
                site_id INT UNSIGNED NULL,
                started_at TIMESTAMP NULL,
                ended_at TIMESTAMP NULL,
                duration_seconds INT UNSIGNED NULL,
                check_in_lat DECIMAL(10, 8) NOT NULL,
                check_in_lng DECIMAL(11, 8) NOT NULL,
                check_in_accuracy_meters SMALLINT UNSIGNED NULL,
                check_out_lat DECIMAL(10, 8) NULL,
                check_out_lng DECIMAL(11, 8) NULL,
                check_out_accuracy_meters SMALLINT UNSIGNED NULL,
                attendance_log_id BIGINT UNSIGNED NULL,
                validated_by INT UNSIGNED NULL,
                validated_at TIMESTAMP NULL,
                validation_note TEXT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'detected',
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL
            )
            SQL);

        DB::statement(<<<'SQL'
            CREATE TABLE employee_location_events (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                employee_id INT UNSIGNED NOT NULL,
                company_id CHAR(36) NOT NULL,
                geo_session_id BIGINT UNSIGNED NULL,
                event_type VARCHAR(30) NOT NULL,
                latitude DECIMAL(10, 8) NULL,
                longitude DECIMAL(11, 8) NULL,
                accuracy_meters SMALLINT UNSIGNED NULL,
                device_timestamp TIMESTAMP NULL,
                metadata JSON NULL,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL
            )
            SQL);

        DB::statement(<<<'SQL'
            CREATE TABLE attendance_mode_settings (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                company_id CHAR(36) NOT NULL UNIQUE,
                forced_mode VARCHAR(20) NULL,
                gps_enabled TINYINT(1) NOT NULL DEFAULT 0,
                latitude DECIMAL(10, 8) NULL,
                longitude DECIMAL(11, 8) NULL,
                radius_meters SMALLINT UNSIGNED NOT NULL DEFAULT 100,
                allow_employee_override TINYINT(1) NOT NULL DEFAULT 1,
                requires_punch_photo TINYINT(1) NOT NULL DEFAULT 0,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL
            )
            SQL);

        DB::statement(<<<'SQL'
            CREATE TABLE employee_attendance_preferences (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                employee_id INT UNSIGNED NOT NULL,
                preferred_mode VARCHAR(20) NOT NULL DEFAULT 'manual',
                gps_consent_given TINYINT(1) NOT NULL DEFAULT 0,
                gps_consent_at TIMESTAMP NULL,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL
            )
            SQL);
    }

    private function geoEvent(array $overrides = []): array
    {
        return array_merge([
            'event_type' => 'zone_enter',
            'latitude' => 36.7538,      // Alger
            'longitude' => 3.0588,
            'accuracy_meters' => 12,
        ], $overrides);
    }

    // ── GeoAttendanceController::event ───────────────────────────────────────

    public function test_zone_enter_creates_a_session(): void
    {
        Sanctum::actingAs($this->employee);

        $response = $this->postJson('/api/v1/smart-attendance/geo-events', $this->geoEvent());

        $response->assertStatus(201)
            ->assertJsonPath('data.status', GeoAttendanceSession::STATUS_DETECTED);

        $this->assertDatabaseHas('geo_attendance_sessions', [
            'employee_id' => $this->employee->id,
            'company_id' => $this->company->id,
            'status' => GeoAttendanceSession::STATUS_DETECTED,
        ]);
        $this->assertDatabaseHas('employee_location_events', [
            'employee_id' => $this->employee->id,
            'event_type' => 'zone_enter',
        ]);
    }

    public function test_duplicate_zone_enter_returns_409(): void
    {
        Sanctum::actingAs($this->employee);

        $this->postJson('/api/v1/smart-attendance/geo-events', $this->geoEvent())->assertStatus(201);

        $response = $this->postJson('/api/v1/smart-attendance/geo-events', $this->geoEvent());

        $response->assertStatus(409)
            ->assertJsonPath('code', 'SESSION_ALREADY_OPEN');

        $this->assertSame(1, GeoAttendanceSession::where('employee_id', $this->employee->id)->count());
    }

    public function test_zone_enter_outside_geofence_returns_422(): void
    {
        Sanctum::actingAs($this->employee);

        // Géofence entreprise : centre Alger, rayon 100 m.
        $this->company->forceFill([
            'metadata' => [
                'attendance_geofence' => [
                    'lat' => 36.7538,
                    'lng' => 3.0588,
                    'radius_meters' => 100,
                ],
            ],
        ])->save();

        // Position très loin (Oran) → hors zone.
        $response = $this->postJson('/api/v1/smart-attendance/geo-events', $this->geoEvent([
            'latitude' => 35.6971,
            'longitude' => -0.6308,
        ]));

        $response->assertStatus(422)
            ->assertJsonPath('code', 'OUTSIDE_GEOFENCE');

        $this->assertDatabaseHas('employee_location_events', [
            'employee_id' => $this->employee->id,
            'event_type' => 'outside_zone',
        ]);
        $this->assertSame(0, GeoAttendanceSession::where('employee_id', $this->employee->id)->count());
    }

    public function test_zone_exit_closes_the_open_session(): void
    {
        Sanctum::actingAs($this->employee);

        $this->postJson('/api/v1/smart-attendance/geo-events', $this->geoEvent())->assertStatus(201);

        $response = $this->postJson('/api/v1/smart-attendance/geo-events', $this->geoEvent([
            'event_type' => 'zone_exit',
        ]));

        $response->assertStatus(201)
            ->assertJsonPath('data.status', GeoAttendanceSession::STATUS_PENDING_VALIDATION);

        $session = GeoAttendanceSession::where('employee_id', $this->employee->id)->first();
        $this->assertNotNull($session->ended_at);
        $this->assertNotNull($session->duration_seconds);
    }

    public function test_zone_exit_without_open_session_is_idempotent(): void
    {
        Sanctum::actingAs($this->employee);

        $response = $this->postJson('/api/v1/smart-attendance/geo-events', $this->geoEvent([
            'event_type' => 'zone_exit',
        ]));

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Event processed (no open session found for exit).');
    }

    public function test_geo_event_rejects_unknown_event_type(): void
    {
        Sanctum::actingAs($this->employee);

        $this->postJson('/api/v1/smart-attendance/geo-events', $this->geoEvent([
            'event_type' => 'teleport',
        ]))->assertStatus(422);
    }

    // ── GeoSessionController ─────────────────────────────────────────────────

    public function test_my_sessions_lists_only_own_sessions(): void
    {
        Sanctum::actingAs($this->employee);

        $this->postJson('/api/v1/smart-attendance/geo-events', $this->geoEvent())->assertStatus(201);
        // Session d'un autre employé — ne doit pas apparaître.
        $other = Employee::factory()->create(['company_id' => $this->company->id, 'status' => 'active']);
        GeoAttendanceSession::create([
            'employee_id' => $other->id,
            'company_id' => $this->company->id,
            'started_at' => now(),
            'check_in_lat' => 36.7538,
            'check_in_lng' => 3.0588,
            'status' => GeoAttendanceSession::STATUS_DETECTED,
        ]);

        $response = $this->getJson('/api/v1/smart-attendance/my-sessions');

        $response->assertOk();
        $sessions = $response->json('data');
        $this->assertCount(1, $sessions);
        $this->assertSame($this->employee->id, $sessions[0]['employee']['id']);
    }

    public function test_sessions_index_requires_manager_role(): void
    {
        // Employé ordinary → 403.
        Sanctum::actingAs($this->employee);
        $this->getJson('/api/v1/smart-attendance/sessions')->assertForbidden();

        // Manager RH → OK.
        Sanctum::actingAs($this->managerRh);
        $this->getJson('/api/v1/smart-attendance/sessions')->assertOk();
    }

    public function test_manager_can_approve_a_session(): void
    {
        Sanctum::actingAs($this->employee);
        $this->postJson('/api/v1/smart-attendance/geo-events', $this->geoEvent())->assertStatus(201);

        $session = GeoAttendanceSession::where('employee_id', $this->employee->id)->first();
        // Fermer d'abord (approve ne s'applique qu'aux sessions fermées).
        $this->postJson('/api/v1/smart-attendance/geo-events', $this->geoEvent([
            'event_type' => 'zone_exit',
        ]))->assertStatus(201);

        Sanctum::actingAs($this->managerRh);
        $response = $this->postJson("/api/v1/smart-attendance/sessions/{$session->id}/approve");

        $response->assertOk();
        $this->assertDatabaseHas('geo_attendance_sessions', [
            'id' => $session->id,
            'status' => GeoAttendanceSession::STATUS_APPROVED,
        ]);
    }

    // ── AttendanceModeController ─────────────────────────────────────────────

    public function test_config_returns_default_mode_without_settings(): void
    {
        Sanctum::actingAs($this->employee);

        $response = $this->getJson('/api/v1/smart-attendance/config');

        $response->assertOk()
            ->assertJsonPath('data.mode', 'manual')
            ->assertJsonPath('data.gps_enabled', false)
            ->assertJsonPath('data.can_override', true);
    }

    public function test_config_returns_forced_company_mode(): void
    {
        DB::table('attendance_mode_settings')->insert([
            'company_id' => $this->company->id,
            'forced_mode' => 'gps_auto',
            'gps_enabled' => 1,
            'latitude' => 36.7538,
            'longitude' => 3.0588,
            'radius_meters' => 200,
            'allow_employee_override' => 0,
        ]);

        Sanctum::actingAs($this->employee);

        $response = $this->getJson('/api/v1/smart-attendance/config');

        $response->assertOk()
            ->assertJsonPath('data.mode', 'gps_auto')
            ->assertJsonPath('data.gps_enabled', true)
            ->assertJsonPath('data.can_override', false)
            ->assertJsonPath('data.requires_consent', true);
    }

    public function test_update_preference_is_blocked_when_company_forces_a_mode(): void
    {
        DB::table('attendance_mode_settings')->insert([
            'company_id' => $this->company->id,
            'forced_mode' => 'qr',
            'gps_enabled' => 0,
            'allow_employee_override' => 0,
        ]);

        Sanctum::actingAs($this->employee);

        $response = $this->putJson('/api/v1/smart-attendance/preferences', [
            'preferred_mode' => 'manual',
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('code', 'COMPANY_MODE_FORCED');
    }
}
