<?php

declare(strict_types=1);

namespace Tests\Feature\SmartAttendance;

use App\Models\Company;
use App\Core\Auth\Domain\Models\Employee;
use App\Models\Schedule;
use App\Modules\SmartAttendance\Domain\Models\GeoAttendanceSession;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\Support\CreatesSmartAttendanceSchema;
use Tests\TestCase;

/**
 * Tests Feature — Isolation multi-tenant
 *
 * Vérifie qu'un employé/manager ne peut voir que les données
 * de sa propre company_id, jamais celles d'autres tenants.
 */
class MultiTenantIsolationTest extends TestCase
{
    use CreatesMvpSchema;
    use CreatesSmartAttendanceSchema;

    // Company A
    private Company $companyA;
    private Employee $employeeA;
    private Employee $managerA;

    // Company B
    private Company $companyB;
    private Employee $employeeB;
    private Employee $managerB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();
        $this->createSmartAttendanceTables();

        // ── Company A ─────────────────────────────────────────────────────
        $this->companyA = Company::query()->create([
            'name'         => 'CompanyA',
            'slug'         => 'company-a-iso',
            'sector'       => 'tech',
            'country'      => 'DZ',
            'city'         => 'Alger',
            'email'        => 'a@iso.test',
            'schema_name'  => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status'       => 'active',
            'timezone'     => 'UTC',
            'metadata'     => [
                'attendance_geofence' => [
                    'lat'           => 36.7538,
                    'lng'           => 3.0588,
                    'radius_meters' => 200,
                ],
            ],
        ]);

        $scheduleA = Schedule::query()->create([
            'company_id'               => $this->companyA->id,
            'name'                     => 'Standard',
            'start_time'               => '08:00:00',
            'end_time'                 => '17:00:00',
            'late_tolerance_minutes'   => 15,
            'overtime_threshold_daily' => 8.0,
            'is_default'               => true,
        ]);

        $this->employeeA = Employee::query()->create([
            'company_id'    => $this->companyA->id,
            'schedule_id'   => $scheduleA->id,
            'email'         => 'emp@company-a.test',
            'password_hash' => Hash::make('password'),
            'role'          => 'employee',
            'status'        => 'active',
        ]);

        $this->managerA = Employee::query()->create([
            'company_id'    => $this->companyA->id,
            'schedule_id'   => $scheduleA->id,
            'email'         => 'manager@company-a.test',
            'password_hash' => Hash::make('password'),
            'role'          => 'manager',
            'manager_role'  => 'rh',
            'status'        => 'active',
        ]);

        // ── Company B ─────────────────────────────────────────────────────
        $this->companyB = Company::query()->create([
            'name'         => 'CompanyB',
            'slug'         => 'company-b-iso',
            'sector'       => 'finance',
            'country'      => 'DZ',
            'city'         => 'Oran',
            'email'        => 'b@iso.test',
            'schema_name'  => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status'       => 'active',
            'timezone'     => 'UTC',
            'metadata'     => [
                'attendance_geofence' => [
                    'lat'           => 35.6906,
                    'lng'           => -0.6347,
                    'radius_meters' => 300,
                ],
            ],
        ]);

        $scheduleB = Schedule::query()->create([
            'company_id'               => $this->companyB->id,
            'name'                     => 'Standard',
            'start_time'               => '08:00:00',
            'end_time'                 => '17:00:00',
            'late_tolerance_minutes'   => 15,
            'overtime_threshold_daily' => 8.0,
            'is_default'               => true,
        ]);

        $this->employeeB = Employee::query()->create([
            'company_id'    => $this->companyB->id,
            'schedule_id'   => $scheduleB->id,
            'email'         => 'emp@company-b.test',
            'password_hash' => Hash::make('password'),
            'role'          => 'employee',
            'status'        => 'active',
        ]);

        $this->managerB = Employee::query()->create([
            'company_id'    => $this->companyB->id,
            'schedule_id'   => $scheduleB->id,
            'email'         => 'manager@company-b.test',
            'password_hash' => Hash::make('password'),
            'role'          => 'manager',
            'manager_role'  => 'rh',
            'status'        => 'active',
        ]);
    }

    protected function tearDown(): void
    {
        $this->dropSmartAttendanceTables();
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function createSessionForEmployee(Employee $employee, Company $company): GeoAttendanceSession
    {
        /** @var GeoAttendanceSession */
        return GeoAttendanceSession::query()->create([
            'employee_id'      => $employee->id,
            'company_id'       => $company->id,
            'started_at'       => now()->subHours(2),
            'ended_at'         => now()->subHour(),
            'duration_seconds' => 3600,
            'check_in_lat'     => 36.7539,
            'check_in_lng'     => 3.0589,
            'status'           => GeoAttendanceSession::STATUS_PENDING_VALIDATION,
        ]);
    }

    // ── Tests ────────────────────────────────────────────────────────────────

    /**
     * GET /my-sessions ne retourne que les sessions de la company de l'employé connecté.
     */
    public function test_employee_cannot_see_sessions_of_other_company(): void
    {
        // Créer une session pour A et une pour B
        $sessionA = $this->createSessionForEmployee($this->employeeA, $this->companyA);
        $sessionB = $this->createSessionForEmployee($this->employeeB, $this->companyB);

        Sanctum::actingAs($this->employeeA);

        $response = $this->getJson('/api/v1/smart-attendance/my-sessions');

        $response->assertStatus(200);

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($sessionA->id, $ids, 'La session A doit être visible pour employé A');
        $this->assertNotContains($sessionB->id, $ids, 'La session B ne doit PAS être visible pour employé A');
    }

    /**
     * GET /sessions (dashboard manager) ne montre que les sessions
     * de la company du manager connecté.
     */
    public function test_manager_dashboard_only_shows_own_company_sessions(): void
    {
        $sessionA = $this->createSessionForEmployee($this->employeeA, $this->companyA);
        $sessionB = $this->createSessionForEmployee($this->employeeB, $this->companyB);

        Sanctum::actingAs($this->managerA);

        $response = $this->getJson('/api/v1/smart-attendance/sessions');

        $response->assertStatus(200);

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($sessionA->id, $ids, 'La session A doit être visible pour manager A');
        $this->assertNotContains($sessionB->id, $ids, 'La session B ne doit PAS être visible pour manager A');
    }

    /**
     * Un événement GPS utilise la géofence de la bonne company.
     * L'employé B (géofence = Oran) qui envoie des coordonnées d'Oran
     * doit créer une session, alors que les coordonnées d'Alger seraient hors zone.
     */
    public function test_geo_event_uses_correct_tenant_geofence(): void
    {
        // Employé B envoie coordonnées dans la zone d'Oran (companyB geofence)
        $latOran = 35.6910;
        $lngOran = -0.6350;

        Sanctum::actingAs($this->employeeB);

        $response = $this->postJson('/api/v1/smart-attendance/geo-events', [
            'event_type'      => 'zone_enter',
            'latitude'        => $latOran,
            'longitude'       => $lngOran,
            'accuracy_meters' => 10,
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.status', GeoAttendanceSession::STATUS_DETECTED);

        // La session doit être liée à companyB
        $session = GeoAttendanceSession::query()->findOrFail($response->json('data.session_id'));
        $this->assertSame((string) $this->companyB->id, $session->company_id);

        // L'employé A (géofence = Alger) ne voit pas cette session
        Sanctum::actingAs($this->employeeA);
        $mySessionsResponse = $this->getJson('/api/v1/smart-attendance/my-sessions');
        $ids = collect($mySessionsResponse->json('data'))->pluck('id')->all();
        $this->assertNotContains($session->id, $ids);
    }
}
