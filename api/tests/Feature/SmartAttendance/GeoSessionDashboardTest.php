<?php

declare(strict_types=1);

namespace Tests\Feature\SmartAttendance;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Planning\Domain\Models\Schedule;
use App\Modules\SmartAttendance\Domain\Models\EmployeeLocationEvent;
use App\Modules\SmartAttendance\Domain\Models\GeoAttendanceSession;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\Support\CreatesSmartAttendanceSchema;
use Tests\TestCase;

/**
 * Tests Feature — Dashboard Manager/RH (sessions GPS)
 *
 * Endpoints :
 *   GET /api/v1/smart-attendance/sessions                      (liste paginée)
 *   GET /api/v1/smart-attendance/sessions?status=...           (filtre statut)
 *   GET /api/v1/smart-attendance/sessions?date_from=&date_to=  (filtre dates)
 *   GET /api/v1/smart-attendance/sessions/{id}                 (détail)
 *   GET /api/v1/smart-attendance/dashboard                     (stats du jour)
 */
class GeoSessionDashboardTest extends TestCase
{
    use CreatesMvpSchema;
    use CreatesSmartAttendanceSchema;

    private Company $company;
    private Employee $employee;
    private Employee $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();
        $this->createSmartAttendanceTables();

        $this->company = Company::query()->create([
            'name'         => 'DashboardCorp',
            'slug'         => 'dashboard-corp',
            'sector'       => 'tech',
            'country'      => 'DZ',
            'city'         => 'Alger',
            'email'        => 'dash@corp.test',
            'schema_name'  => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status'       => 'active',
            'timezone'     => 'UTC',
        ]);

        $schedule = Schedule::query()->create([
            'company_id'               => $this->company->id,
            'name'                     => 'Standard',
            'start_time'               => '08:00:00',
            'end_time'                 => '17:00:00',
            'late_tolerance_minutes'   => 15,
            'overtime_threshold_daily' => 8.0,
            'is_default'               => true,
        ]);

        $this->employee = Employee::query()->create([
            'company_id'    => $this->company->id,
            'schedule_id'   => $schedule->id,
            'email'         => 'emp@dashboard.test',
            'password_hash' => Hash::make('password'),
            'role'          => 'employee',
            'status'        => 'active',
        ]);

        $this->manager = Employee::query()->create([
            'company_id'    => $this->company->id,
            'schedule_id'   => $schedule->id,
            'email'         => 'manager@dashboard.test',
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

    private function createSession(array $override = []): GeoAttendanceSession
    {
        /** @var GeoAttendanceSession */
        return GeoAttendanceSession::query()->create(array_merge([
            'employee_id'      => $this->employee->id,
            'company_id'       => $this->company->id,
            'started_at'       => now()->subHours(8),
            'ended_at'         => now()->subHour(),
            'duration_seconds' => 7 * 3600,
            'check_in_lat'     => 36.7539,
            'check_in_lng'     => 3.0589,
            'status'           => GeoAttendanceSession::STATUS_PENDING_VALIDATION,
        ], $override));
    }

    // ── Tests ────────────────────────────────────────────────────────────────

    /**
     * GET /sessions retourne une liste paginée des sessions de la company.
     */
    public function test_manager_can_list_sessions(): void
    {
        // Créer 3 sessions
        for ($i = 0; $i < 3; $i++) {
            $this->createSession();
        }

        Sanctum::actingAs($this->manager);

        $response = $this->getJson('/api/v1/smart-attendance/sessions');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [['id', 'employee', 'status', 'started_at', 'ended_at', 'duration_seconds']],
            'meta' => ['total', 'current_page', 'last_page'],
        ]);

        $this->assertGreaterThanOrEqual(3, $response->json('meta.total'));
    }

    /**
     * GET /sessions?per_page=... doit être borné à 100 comme tous les autres
     * endpoints paginés du repo, pour éviter qu'un appelant authentifié
     * demande une page arbitrairement grande.
     * Voir docs/security/AUDIT_API_2026-07-19.md (revue de suivi 2026-07-19b).
     */
    public function test_per_page_is_clamped_to_one_hundred(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->createSession();
        }

        Sanctum::actingAs($this->manager);

        $response = $this->getJson('/api/v1/smart-attendance/sessions?per_page=999999');

        $response->assertStatus(200);
        $response->assertJsonPath('meta.per_page', 100);
    }

    /**
     * GET /sessions?per_page=0 (ou négatif) doit être ramené à 1, pas planter
     * ni retourner une page vide anormale.
     */
    public function test_per_page_is_floored_to_one(): void
    {
        $this->createSession();

        Sanctum::actingAs($this->manager);

        $response = $this->getJson('/api/v1/smart-attendance/sessions?per_page=0');

        $response->assertStatus(200);
        $response->assertJsonPath('meta.per_page', 1);
    }

    /**
     * GET /sessions?status=pending_validation ne retourne que les sessions
     * avec ce statut.
     */
    public function test_sessions_filtered_by_status(): void
    {
        // Une session pending + une approuvée
        $this->createSession(['status' => GeoAttendanceSession::STATUS_PENDING_VALIDATION]);
        $this->createSession(['status' => GeoAttendanceSession::STATUS_APPROVED]);

        Sanctum::actingAs($this->manager);

        $response = $this->getJson('/api/v1/smart-attendance/sessions?status=pending_validation');

        $response->assertStatus(200);

        $statuses = collect($response->json('data'))->pluck('status')->unique()->all();
        $this->assertSame([GeoAttendanceSession::STATUS_PENDING_VALIDATION], $statuses);
    }

    /**
     * GET /sessions?date_from=...&date_to=... filtre sur les dates de début.
     */
    public function test_sessions_filtered_by_date_range(): void
    {
        // Session hier
        $yesterday = Carbon::yesterday()->setTime(9, 0, 0);
        $this->createSession([
            'started_at' => $yesterday,
            'ended_at'   => $yesterday->copy()->addHours(8),
        ]);

        // Session il y a une semaine
        $lastWeek = Carbon::now()->subDays(7)->setTime(9, 0, 0);
        $this->createSession([
            'started_at' => $lastWeek,
            'ended_at'   => $lastWeek->copy()->addHours(8),
        ]);

        Sanctum::actingAs($this->manager);

        // Filtrer uniquement sur hier
        $dateFrom = Carbon::yesterday()->toDateString();
        $dateTo   = Carbon::yesterday()->toDateString();

        $response = $this->getJson("/api/v1/smart-attendance/sessions?date_from={$dateFrom}&date_to={$dateTo}");

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertCount(1, $data, 'Seulement la session d\'hier doit être retournée');
    }

    /**
     * GET /sessions/{id} retourne le détail d'une session avec location_events.
     */
    public function test_manager_can_view_session_detail(): void
    {
        $session = $this->createSession();

        // Ajouter un événement de localisation
        EmployeeLocationEvent::query()->create([
            'employee_id'     => $this->employee->id,
            'company_id'      => $this->company->id,
            'geo_session_id'  => $session->id,
            'event_type'      => EmployeeLocationEvent::TYPE_ZONE_ENTER,
            'latitude'        => 36.7539,
            'longitude'       => 3.0589,
            'accuracy_meters' => 10,
        ]);

        Sanctum::actingAs($this->manager);

        $response = $this->getJson("/api/v1/smart-attendance/sessions/{$session->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.id', $session->id);
        $response->assertJsonStructure([
            'data' => [
                'id',
                'employee',
                'status',
                'started_at',
                'ended_at',
                'duration_seconds',
                'check_in_lat',
                'check_in_lng',
                'location_events' => [
                    ['event_type', 'latitude', 'longitude', 'accuracy_meters'],
                ],
            ],
        ]);

        $locationEvents = $response->json('data.location_events');
        $this->assertNotEmpty($locationEvents, 'location_events doit contenir les événements');
        $this->assertSame(EmployeeLocationEvent::TYPE_ZONE_ENTER, $locationEvents[0]['event_type']);
    }

    /**
     * GET /dashboard retourne les statistiques du jour (stats par statut).
     */
    public function test_dashboard_returns_today_stats(): void
    {
        $this->travelTo(Carbon::parse('2026-06-29 10:00:00', 'UTC'));

        // Créer des sessions aujourd'hui avec différents statuts
        $this->createSession([
            'started_at' => now()->subHours(3),
            'ended_at'   => now()->subHour(),
            'status'     => GeoAttendanceSession::STATUS_PENDING_VALIDATION,
        ]);
        $this->createSession([
            'started_at' => now()->subHours(5),
            'ended_at'   => now()->subHours(2),
            'status'     => GeoAttendanceSession::STATUS_APPROVED,
        ]);

        Sanctum::actingAs($this->manager);

        $response = $this->getJson('/api/v1/smart-attendance/dashboard');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'today',
                'stats',
                'pending',
            ],
        ]);

        $data = $response->json('data');
        $this->assertSame('2026-06-29', $data['today']);
        $this->assertIsArray($data['stats']);
        $this->assertIsArray($data['pending']);

        // Vérifier que les stats incluent les statuts de ce jour
        $this->assertArrayHasKey(GeoAttendanceSession::STATUS_PENDING_VALIDATION, $data['stats']);
        $this->assertArrayHasKey(GeoAttendanceSession::STATUS_APPROVED, $data['stats']);
    }
}

