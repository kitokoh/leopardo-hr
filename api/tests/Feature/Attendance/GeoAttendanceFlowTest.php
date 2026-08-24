<?php

declare(strict_types=1);

namespace Tests\Feature\Attendance;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Attendance\Domain\Models\GeoAttendanceSession;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #3887 — le module Attendance (présence intelligente GPS) était
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
class GeoAttendanceFlowTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    private Employee $employee;

    private Employee $managerRh;

    protected function setUp(): void
    {
        parent::setUp();

        // #4243 : RefreshTenantDatabase exécute les migrations tenant —
        // dont 2026_06_29_0002xx qui créent les 4 tables géo Attendance
        // avec le schéma PostgreSQL. Plus de DDL manuel (l'ancien était du
        // MySQL → SQLSTATE 42601 sur pgsql → 12 tests rouges).

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ']);
        $this->company = $company;
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $this->company->id,
            'role' => 'employee',
            'status' => 'active',
        ]);
        $this->employee = $employee;
        /** @var Employee $managerRh */
        $managerRh = Employee::factory()->create([
            'company_id' => $this->company->id,
            'role' => 'manager',
            'manager_role' => 'rh',
            'status' => 'active',
        ]);
        $this->managerRh = $managerRh;
    }

    // #4243 : plus de tearDown custom ni de schéma DDL manuel —
    // RefreshTenantDatabase (migrate:fresh) recrée les tables depuis les
    // migrations tenant (2026_06_29_0002xx).

    /** @param array<string, mixed> $overrides    /** @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
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

        $response = $this->postJson('/api/v1/attendance/geo-events', $this->geoEvent());

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

        $this->postJson('/api/v1/attendance/geo-events', $this->geoEvent())->assertStatus(201);

        $response = $this->postJson('/api/v1/attendance/geo-events', $this->geoEvent());

        $response->assertStatus(409)
            ->assertJsonPath('code', 'SESSION_ALREADY_OPEN');

        $this->assertSame(1, GeoAttendanceSession::where('employee_id', $this->employee->id)->count());
    }

    public function test_zone_enter_outside_geofence_returns_422(): void
    {
        Sanctum::actingAs($this->employee);

        // Géofence entreprise : centre Alger, rayon 100 m (contrat lu par
        // AttendanceGeofenceService::resolveTarget → company.metadata).
        // NB : le hook `saved` de Employee (syncUserLookup) eager-loade la
        // relation `company` AU MOMENT de la création — la company tenue par
        // l'employé est donc périmée après un update de metadata. Le
        // TenantMiddleware lie `$employee->company` : sans reload, la géofence
        // n'est jamais vue → 201 au lieu de 422 (issue #5201).
        $this->company->forceFill([
            'metadata' => [
                'attendance_geofence' => [
                    'lat' => 36.7538,
                    'lng' => 3.0588,
                    'radius_meters' => 100,
                ],
            ],
        ])->save();
        $this->employee->unsetRelation('company');

        // Position très loin (Oran) → hors zone.
        $response = $this->postJson('/api/v1/attendance/geo-events', $this->geoEvent([
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

        $this->postJson('/api/v1/attendance/geo-events', $this->geoEvent())->assertStatus(201);

        $response = $this->postJson('/api/v1/attendance/geo-events', $this->geoEvent([
            'event_type' => 'zone_exit',
        ]));

        $response->assertStatus(201)
            ->assertJsonPath('data.status', GeoAttendanceSession::STATUS_PENDING_VALIDATION);

        /** @var GeoAttendanceSession $session */
        $session = GeoAttendanceSession::where('employee_id', $this->employee->id)->firstOrFail();
        $this->assertNotNull($session->ended_at);
        $this->assertNotNull($session->duration_seconds);
    }

    public function test_zone_exit_without_open_session_is_idempotent(): void
    {
        Sanctum::actingAs($this->employee);

        $response = $this->postJson('/api/v1/attendance/geo-events', $this->geoEvent([
            'event_type' => 'zone_exit',
        ]));

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Event processed (no open session found for exit).');
    }

    public function test_geo_event_rejects_unknown_event_type(): void
    {
        Sanctum::actingAs($this->employee);

        $this->postJson('/api/v1/attendance/geo-events', $this->geoEvent([
            'event_type' => 'teleport',
        ]))->assertStatus(422);
    }

    // ── GeoSessionController ─────────────────────────────────────────────────

    public function test_my_sessions_lists_only_own_sessions(): void
    {
        Sanctum::actingAs($this->employee);

        $this->postJson('/api/v1/attendance/geo-events', $this->geoEvent())->assertStatus(201);
        // Session d'un autre employé — ne doit pas apparaître.
        /** @var Employee $other */
        $other = Employee::factory()->create(['company_id' => $this->company->id, 'status' => 'active']);
        GeoAttendanceSession::create([
            'employee_id' => $other->id,
            'company_id' => $this->company->id,
            'started_at' => now(),
            'check_in_lat' => 36.7538,
            'check_in_lng' => 3.0588,
            'status' => GeoAttendanceSession::STATUS_DETECTED,
        ]);

        $response = $this->getJson('/api/v1/attendance/my-sessions');

        $response->assertOk();
        $sessions = $response->json('data');
        $this->assertCount(1, $sessions);
        $this->assertSame($this->employee->id, $sessions[0]['employee']['id']);
    }

    public function test_sessions_index_requires_manager_role(): void
    {
        // Employé ordinary → 403.
        Sanctum::actingAs($this->employee);
        $this->getJson('/api/v1/attendance/geo-sessions')->assertForbidden();

        // Manager RH → OK.
        Sanctum::actingAs($this->managerRh);
        $this->getJson('/api/v1/attendance/geo-sessions')->assertOk();
    }

    public function test_manager_can_approve_a_session(): void
    {
        Sanctum::actingAs($this->employee);
        $this->postJson('/api/v1/attendance/geo-events', $this->geoEvent())->assertStatus(201);

        $session = GeoAttendanceSession::where('employee_id', $this->employee->id)->first();
        // Fermer d'abord (approve ne s'applique qu'aux sessions fermées).
        $this->postJson('/api/v1/attendance/geo-events', $this->geoEvent([
            'event_type' => 'zone_exit',
        ]))->assertStatus(201);

        Sanctum::actingAs($this->managerRh);
        $this->assertNotNull($session);
        $response = $this->postJson("/api/v1/attendance/geo-sessions/{$session->id}/approve");

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

        $response = $this->getJson('/api/v1/attendance/config');

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

        $response = $this->getJson('/api/v1/attendance/config');

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

        $response = $this->putJson('/api/v1/attendance/preferences', [
            'preferred_mode' => 'manual',
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('code', 'COMPANY_MODE_FORCED');
    }
}
