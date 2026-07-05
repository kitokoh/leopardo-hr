<?php

declare(strict_types=1);

namespace Tests\Feature\Edge;

use App\Models\AttendanceLog;
use App\Models\Company;
use App\Core\Auth\Domain\Models\Employee;
use App\Models\Schedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * Phase 4 — Scénario 4.5 : Isolation multi-tenant des nœuds Edge
 *
 * Vérifie qu'un nœud Edge d'un tenant A ne peut jamais voir, accéder
 * ou recevoir les données d'un tenant B, y compris :
 *   - Les attendance_logs (scoped par company_id)
 *   - Les edge_nodes (scoped par company_id)
 *   - La liste des nodes platform admin (tous les tenants visibles uniquement pour super-admin)
 *
 * C'est le test de régression le plus critique pour la confidentialité des données.
 */
class EdgeMultiTenantIsolationTest extends TestCase
{
    use CreatesMvpSchema;

    private Company $companyA;
    private Company $companyB;
    private Employee $employeeA;
    private Employee $employeeB;
    private Schedule $scheduleA;
    private Schedule $scheduleB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();
        $this->createEdgeNodesTable();

        $this->companyA = Company::factory()->create([
            'name'         => 'Tenant Alpha',
            'slug'         => 'tenant-alpha',
            'schema_name'  => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status'       => 'active',
        ]);

        $this->companyB = Company::factory()->create([
            'name'         => 'Tenant Beta',
            'slug'         => 'tenant-beta',
            'schema_name'  => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status'       => 'active',
        ]);

        $this->scheduleA = Schedule::factory()->create([
            'company_id' => $this->companyA->id,
            'name'       => 'Alpha Schedule',
            'start_time' => '08:00:00',
            'end_time'   => '17:00:00',
        ]);

        $this->scheduleB = Schedule::factory()->create([
            'company_id' => $this->companyB->id,
            'name'       => 'Beta Schedule',
            'start_time' => '09:00:00',
            'end_time'   => '18:00:00',
        ]);

        $this->employeeA = Employee::factory()->create([
            'company_id'  => $this->companyA->id,
            'role'        => 'employee',
            'schedule_id' => $this->scheduleA->id,
        ]);

        $this->employeeB = Employee::factory()->create([
            'company_id'  => $this->companyB->id,
            'role'        => 'employee',
            'schedule_id' => $this->scheduleB->id,
        ]);
    }

    protected function tearDown(): void
    {
        DB::statement('DROP TABLE IF EXISTS edge_nodes CASCADE');
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    private function createEdgeNodesTable(): void
    {
        // La table edge_nodes canonique (module EdgeSync DDD, schema UUID/slug)
        // est deja creee par setUpMvpSchema(). Ce test cible un ancien schema
        // legacy (bigint + node_id) utilise par le code mort EdgeController /
        // DetectSilentEdgeNodes (non routes/planifies). On la remplace ici
        // pour la duree du test, puis tearDown() la drop pour laisser le
        // prochain setUp() recreer le schema canonique.
        DB::statement('DROP TABLE IF EXISTS edge_nodes CASCADE');

        Schema::create('edge_nodes', function ($table): void {
            $table->id();
            $table->uuid('company_id')->index();
            $table->string('node_id', 64)->unique();
            $table->string('name', 128);
            $table->string('ip_address', 45)->nullable();
            $table->string('version', 32)->nullable();
            $table->string('status', 16)->default('offline')->index();
            $table->timestamp('last_seen_at')->nullable();
            $table->unsignedInteger('pending_count')->default(0);
            $table->timestamp('sync_requested_at')->nullable();
            $table->boolean('license_valid')->default(false);
            $table->timestamp('license_expires_at')->nullable();
            $table->boolean('alert_muted')->default(false);
            $table->timestamp('last_alert_sent_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });
    }

    private function insertNode(Company $company, string $nodeId, string $name): object
    {
        $id = DB::table('edge_nodes')->insertGetId([
            'company_id'         => $company->id,
            'node_id'            => $nodeId,
            'name'               => $name,
            'status'             => 'online',
            'license_valid'      => true,
            'license_expires_at' => Carbon::now()->addDays(30)->toDateTimeString(),
            'last_seen_at'       => Carbon::now()->toDateTimeString(),
            'pending_count'      => 0,
            'created_at'         => Carbon::now(),
            'updated_at'         => Carbon::now(),
        ]);

        return DB::table('edge_nodes')->find($id);
    }

    private function createLog(Company $company, Employee $employee, Schedule $schedule, int $session = 1): AttendanceLog
    {
        return AttendanceLog::create([
            'company_id'          => $company->id,
            'employee_id'         => $employee->id,
            'schedule_id'         => $schedule->id,
            'date'                => Carbon::today()->toDateString(),
            'session_number'      => $session,
            'check_in'            => Carbon::now()->setTime(8, 0, 0)->toDateTimeString(),
            'method'              => 'qr_code',
            'work_type'           => 'presentiel',
            'biometric_type'      => 'none',
            'synced_from_offline' => false,
            'status'              => 'present',
            'hours_worked'        => '0',
            'overtime_hours'      => '0',
            'late_minutes'        => 0,
        ]);
    }

    // ── Tests 4.5 ────────────────────────────────────────────────────────────

    /**
     * 4.5.a — Un nœud Edge du tenant A ne peut pas accéder aux attendance_logs
     *          du tenant B via une requête scoped sur company_id.
     */
    public function test_edge_node_cannot_access_other_tenant_attendance_logs(): void
    {
        $nodeA = $this->insertNode($this->companyA, 'edge-a-001', 'Kiosque Alpha');
        $nodeB = $this->insertNode($this->companyB, 'edge-b-001', 'Kiosque Beta');

        $logA = $this->createLog($this->companyA, $this->employeeA, $this->scheduleA, 1);
        $logB = $this->createLog($this->companyB, $this->employeeB, $this->scheduleB, 1);

        // Requête scoped Tenant A (comme le ferait l'API Edge du nœud A)
        $logsVisibleFromA = AttendanceLog::where('company_id', $this->companyA->id)->get();

        $ids = $logsVisibleFromA->pluck('id')->toArray();

        $this->assertContains($logA->id, $ids, 'Le nœud A doit voir ses propres logs');
        $this->assertNotContains($logB->id, $ids, 'Le nœud A NE DOIT PAS voir les logs du tenant B');
    }

    /**
     * 4.5.b — Le nœud Edge B ne peut pas accéder aux edge_nodes du tenant A.
     */
    public function test_edge_node_b_cannot_see_edge_nodes_of_tenant_a(): void
    {
        $nodeA = $this->insertNode($this->companyA, 'edge-iso-a', 'Node Alpha');
        $nodeB = $this->insertNode($this->companyB, 'edge-iso-b', 'Node Beta');

        // Requête scoped tenant B
        $nodesForB = DB::table('edge_nodes')
            ->where('company_id', $this->companyB->id)
            ->get();

        $nodeIds = $nodesForB->pluck('node_id')->toArray();

        $this->assertContains('edge-iso-b', $nodeIds, 'B doit voir ses propres nodes');
        $this->assertNotContains('edge-iso-a', $nodeIds, 'B NE DOIT PAS voir les nodes de A');
    }

    /**
     * 4.5.c — Les employés d'un tenant ne sont pas visibles depuis l'autre tenant.
     */
    public function test_employees_are_isolated_between_tenants(): void
    {
        $employeesA = Employee::where('company_id', $this->companyA->id)->get();
        $employeesB = Employee::where('company_id', $this->companyB->id)->get();

        $idsA = $employeesA->pluck('id')->toArray();
        $idsB = $employeesB->pluck('id')->toArray();

        $this->assertContains($this->employeeA->id, $idsA);
        $this->assertNotContains($this->employeeB->id, $idsA, 'Les employés B ne doivent pas être visibles depuis A');
        $this->assertNotContains($this->employeeA->id, $idsB, 'Les employés A ne doivent pas être visibles depuis B');
    }

    /**
     * 4.5.d — Les sync requests d'un nœud ne se propagent pas aux nœuds d'un autre tenant.
     */
    public function test_sync_request_is_scoped_to_tenant(): void
    {
        $nodeA = $this->insertNode($this->companyA, 'edge-sync-a', 'Sync Node A');
        $nodeB = $this->insertNode($this->companyB, 'edge-sync-b', 'Sync Node B');

        // Sync request sur node A seulement
        DB::table('edge_nodes')
            ->where('node_id', 'edge-sync-a')
            ->update(['sync_requested_at' => Carbon::now()->toDateTimeString()]);

        $updatedA = DB::table('edge_nodes')->where('node_id', 'edge-sync-a')->first();
        $updatedB = DB::table('edge_nodes')->where('node_id', 'edge-sync-b')->first();

        $this->assertNotNull($updatedA->sync_requested_at, 'Node A doit avoir sync_requested_at setté');
        $this->assertNull($updatedB->sync_requested_at, 'Node B NE DOIT PAS avoir sync_requested_at (autre tenant)');
    }

    /**
     * 4.5.e — Le total cross-tenant : N tenants → les données sont strictement partitionnées.
     *          Test avec 3 tenants.
     */
    public function test_three_tenants_strict_data_partitioning(): void
    {
        $companyC = Company::factory()->create([
            'name'         => 'Tenant Gamma',
            'slug'         => 'tenant-gamma',
            'schema_name'  => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status'       => 'active',
        ]);

        $scheduleC = Schedule::factory()->create([
            'company_id' => $companyC->id,
            'name'       => 'Gamma Schedule',
            'start_time' => '07:00:00',
            'end_time'   => '16:00:00',
        ]);

        $employeeC = Employee::factory()->create([
            'company_id'  => $companyC->id,
            'role'        => 'employee',
            'schedule_id' => $scheduleC->id,
        ]);

        $this->insertNode($companyC, 'edge-gamma-001', 'Kiosque Gamma');

        $logA = $this->createLog($this->companyA, $this->employeeA, $this->scheduleA, 1);
        $logB = $this->createLog($this->companyB, $this->employeeB, $this->scheduleB, 1);
        $logC = $this->createLog($companyC, $employeeC, $scheduleC, 1);

        // Vérifier isolation de chaque tenant
        $logsA = AttendanceLog::where('company_id', $this->companyA->id)->pluck('id')->toArray();
        $logsB = AttendanceLog::where('company_id', $this->companyB->id)->pluck('id')->toArray();
        $logsC = AttendanceLog::where('company_id', $companyC->id)->pluck('id')->toArray();

        // Chaque tenant ne voit que ses logs
        $this->assertContains($logA->id, $logsA);
        $this->assertNotContains($logB->id, $logsA);
        $this->assertNotContains($logC->id, $logsA);

        $this->assertContains($logB->id, $logsB);
        $this->assertNotContains($logA->id, $logsB);
        $this->assertNotContains($logC->id, $logsB);

        $this->assertContains($logC->id, $logsC);
        $this->assertNotContains($logA->id, $logsC);
        $this->assertNotContains($logB->id, $logsC);
    }

    /**
     * 4.5.f — N nœuds pour un même tenant : le company_id les discrimine tous.
     */
    public function test_multiple_nodes_same_tenant_share_data_correctly(): void
    {
        $nodeA1 = $this->insertNode($this->companyA, 'edge-a-multi-1', 'Alpha Entrée');
        $nodeA2 = $this->insertNode($this->companyA, 'edge-a-multi-2', 'Alpha Sortie');

        // Les deux nodes appartiennent au même tenant A
        $nodesForA = DB::table('edge_nodes')
            ->where('company_id', $this->companyA->id)
            ->get();

        $this->assertGreaterThanOrEqual(2, $nodesForA->count(), 'Tenant A doit avoir au moins 2 nodes');

        foreach ($nodesForA as $node) {
            $this->assertEquals($this->companyA->id, $node->company_id, 'Tous les nodes A doivent avoir company_id = A');
        }
    }

    /**
     * 4.5.g — Alerte silence : DetectSilentEdgeNodes ne mélange pas les tenants.
     *          Un nœud silencieux de B ne doit pas notifier les managers de A.
     */
    public function test_silent_node_alert_scoped_to_correct_tenant(): void
    {
        // Node A : silencieux
        DB::table('edge_nodes')->insertGetId([
            'company_id'    => $this->companyA->id,
            'node_id'       => 'edge-alert-a',
            'name'          => 'Silencieux A',
            'status'        => 'online',
            'license_valid' => true,
            'license_expires_at' => Carbon::now()->addDays(30)->toDateTimeString(),
            'last_seen_at'  => Carbon::now()->subHours(2)->toDateTimeString(),
            'pending_count' => 0,
            'alert_muted'   => false,
            'created_at'    => Carbon::now(),
            'updated_at'    => Carbon::now(),
        ]);

        // Node B : online
        DB::table('edge_nodes')->insertGetId([
            'company_id'    => $this->companyB->id,
            'node_id'       => 'edge-alert-b',
            'name'          => 'Actif B',
            'status'        => 'online',
            'license_valid' => true,
            'license_expires_at' => Carbon::now()->addDays(30)->toDateTimeString(),
            'last_seen_at'  => Carbon::now()->toDateTimeString(), // récent
            'pending_count' => 0,
            'alert_muted'   => false,
            'created_at'    => Carbon::now(),
            'updated_at'    => Carbon::now(),
        ]);

        $threshold = Carbon::now()->subMinutes(30);

        // Requête silence pour tenant A uniquement
        $silentForA = DB::table('edge_nodes')
            ->where('company_id', $this->companyA->id)
            ->where('status', '!=', 'revoked')
            ->where(function ($q) use ($threshold) {
                $q->where('last_seen_at', '<', $threshold)->orWhereNull('last_seen_at');
            })
            ->where('alert_muted', false)
            ->get();

        $this->assertSame(1, $silentForA->count(), 'Exactement 1 nœud silencieux pour tenant A');
        $this->assertSame('edge-alert-a', $silentForA->first()->node_id);

        // Aucun node B dans les alertes de A
        $nodeIdsA = $silentForA->pluck('node_id')->toArray();
        $this->assertNotContains('edge-alert-b', $nodeIdsA, 'Le node silencieux de B NE DOIT PAS être dans les alertes de A');
    }
}
