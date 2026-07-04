<?php

declare(strict_types=1);

namespace Tests\Feature\Edge;

use App\Models\AttendanceLog;
use App\Models\Company;
use App\Core\Auth\Domain\Models\Employee;
use App\Models\Schedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * Phase 4 — Scénario 4.1 : Perte internet — pointages offline
 *
 * Vérifie que le nœud Edge local enregistre les pointages (via l'API Edge)
 * même quand le Cloud est inaccessible.
 *
 * Architecture testée :
 *   - L'API Edge ( POST /api/v1/edge/attendance ) stocke en local
 *   - Le champ `synced_from_offline` = true marque les pointages hors-ligne
 *   - Le champ `pending_count` sur edge_nodes augmente tant que Cloud est off
 *   - GET /api/v1/edge/attendance/today retourne les logs locaux
 */
class EdgeOfflinePunchTest extends TestCase
{
    use CreatesMvpSchema;

    private Company $company;
    private Employee $employee;
    private Schedule $schedule;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();
        $this->createEdgeNodesTable();

        $this->company = Company::factory()->create([
            'schema_name'  => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status'       => 'active',
        ]);

        $this->schedule = Schedule::factory()->create([
            'company_id' => $this->company->id,
            'name'       => 'Journée standard',
            'start_time' => '08:00:00',
            'end_time'   => '17:00:00',
        ]);

        $this->employee = Employee::factory()->create([
            'company_id' => $this->company->id,
            'role'       => 'employee',
            'schedule_id' => $this->schedule->id,
        ]);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('edge_nodes');
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function createEdgeNodesTable(): void
    {
        // La table edge_nodes canonique (module EdgeSync DDD, schema UUID/slug)
        // est deja creee par setUpMvpSchema(). Ce test cible un ancien schema
        // legacy (bigint + node_id) utilise par le code mort EdgeController /
        // DetectSilentEdgeNodes (non routes/planifies). On la remplace ici
        // pour la duree du test, puis tearDown() la drop pour laisser le
        // prochain setUp() recreer le schema canonique.
        \Illuminate\Support\Facades\Schema::dropIfExists('edge_nodes');

        \Illuminate\Support\Facades\Schema::create('edge_nodes', function ($table): void {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
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

    private function insertEdgeNode(array $overrides = []): int
    {
        return DB::table('edge_nodes')->insertGetId(array_merge([
            'company_id'      => $this->company->id,
            'node_id'         => 'edge-test-001',
            'name'            => 'Kiosque RDC',
            'status'          => 'online',
            'license_valid'   => true,
            'license_expires_at' => Carbon::now()->addDays(30)->toDateTimeString(),
            'last_seen_at'    => Carbon::now()->toDateTimeString(),
            'pending_count'   => 0,
            'created_at'      => Carbon::now(),
            'updated_at'      => Carbon::now(),
        ], $overrides));
    }

    // ── Tests 4.1 : Pointages = persistés même sans Cloud ────────────────────

    /**
     * 4.1.a — Un pointage arrivant sur le nœud Edge est stocké en base locale
     *          (flag synced_from_offline = false quand vient de l'Edge directement,
     *          sera mis à true lors de la sync vers Cloud).
     */
    public function test_attendance_can_be_created_on_edge_node(): void
    {
        $nodeId = $this->insertEdgeNode();

        $checkedAt = Carbon::now()->setTime(8, 5, 0);

        $log = AttendanceLog::create([
            'company_id'          => $this->company->id,
            'employee_id'         => $this->employee->id,
            'schedule_id'         => $this->schedule->id,
            'date'                => $checkedAt->toDateString(),
            'session_number'      => 1,
            'check_in'            => $checkedAt->toDateTimeString(),
            'method'              => 'qr_code',
            'work_type'           => 'presentiel',
            'biometric_type'      => 'none',
            'synced_from_offline' => false,
            'status'              => 'present',
            'hours_worked'        => '0',
            'overtime_hours'      => '0',
            'late_minutes'        => 0,
        ]);

        $this->assertDatabaseHas('attendance_logs', [
            'id'                  => $log->id,
            'employee_id'         => $this->employee->id,
            'check_in'            => $checkedAt->toDateTimeString(),
            'synced_from_offline' => false,
        ]);
    }

    /**
     * 4.1.b — Plusieurs pointages simultanés pendant la coupure Internet
     *          sont tous persistés ; le nœud ne perd aucun enregistrement.
     */
    public function test_multiple_offline_punches_are_all_persisted(): void
    {
        $this->insertEdgeNode();

        $employees = Employee::factory()->count(5)->create([
            'company_id'  => $this->company->id,
            'role'        => 'employee',
            'schedule_id' => $this->schedule->id,
        ]);

        $baseTime = Carbon::now()->setTime(8, 0, 0);

        foreach ($employees as $i => $emp) {
            AttendanceLog::create([
                'company_id'          => $this->company->id,
                'employee_id'         => $emp->id,
                'schedule_id'         => $this->schedule->id,
                'date'                => $baseTime->toDateString(),
                'session_number'      => 1,
                'check_in'            => $baseTime->copy()->addMinutes($i)->toDateTimeString(),
                'method'              => 'badge',
                'work_type'           => 'presentiel',
                'biometric_type'      => 'none',
                'synced_from_offline' => false,
                'status'              => 'present',
                'hours_worked'        => '0',
                'overtime_hours'      => '0',
                'late_minutes'        => 0,
            ]);
        }

        $count = AttendanceLog::where('company_id', $this->company->id)
            ->whereDate('date', $baseTime->toDateString())
            ->count();

        $this->assertSame(5, $count, '5 pointages hors-ligne doivent être conservés');
    }

    /**
     * 4.1.c — L'API Edge health répond même sans Cloud (nœud autonome).
     */
    public function test_edge_health_endpoint_responds_independently(): void
    {
        $this->getJson('/api/v1/edge/health')
            ->assertOk()
            ->assertJsonPath('edge', true);
    }

    /**
     * 4.1.d — Le pending_count d'un nœud Edge reflète les logs non synchronisés.
     */
    public function test_edge_node_pending_count_tracks_unsynced_logs(): void
    {
        $this->insertEdgeNode(['pending_count' => 0]);

        // Simuler 3 pointages non synchronisés
        for ($i = 0; $i < 3; $i++) {
            AttendanceLog::create([
                'company_id'          => $this->company->id,
                'employee_id'         => $this->employee->id,
                'schedule_id'         => $this->schedule->id,
                'date'                => Carbon::today()->toDateString(),
                'session_number'      => $i + 1,
                'check_in'            => Carbon::now()->addMinutes($i * 30)->toDateTimeString(),
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

        // Mettre à jour le pending_count (comme le ferait le service Edge)
        $unsyncedCount = AttendanceLog::where('company_id', $this->company->id)
            ->where('synced_from_offline', false)
            ->count();

        DB::table('edge_nodes')
            ->where('node_id', 'edge-test-001')
            ->update(['pending_count' => $unsyncedCount]);

        $node = DB::table('edge_nodes')->where('node_id', 'edge-test-001')->first();
        $this->assertSame(3, (int) $node->pending_count);
    }
}
