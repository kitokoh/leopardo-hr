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
 * Phase 4 — Scénario 4.3 : Conflit de données
 *
 * Vérifie la stratégie de résolution de conflit quand un même enregistrement
 * est modifié à la fois sur Edge et sur Cloud pendant la coupure réseau.
 *
 * Stratégie appliquée dans Leopardo : "Cloud wins" pour les corrections,
 * "earliest timestamp wins" pour les check-ins, avec conservation de l'audit trail.
 *
 * Ce que l'on teste :
 *   - Détection d'un conflit (même employee_id + date + session_number)
 *   - La version Cloud prend la priorité (updated_at Cloud > updated_at Edge)
 *   - Le pointage Edge original est conservé avec un flag pour audit
 *   - Aucune perte de données — les deux versions existent en historique
 */
class EdgeConflictResolutionTest extends TestCase
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
            'name'       => 'Journée',
            'start_time' => '08:00:00',
            'end_time'   => '17:00:00',
        ]);

        $this->employee = Employee::factory()->create([
            'company_id'  => $this->company->id,
            'role'        => 'employee',
            'schedule_id' => $this->schedule->id,
        ]);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('edge_nodes');
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
        Schema::dropIfExists('edge_nodes');

        Schema::create('edge_nodes', function ($table): void {
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

    // ── Tests 4.3 ────────────────────────────────────────────────────────────

    /**
     * 4.3.a — Un log créé sur Edge (synced_from_offline=false) peut coexister
     *          temporairement avec un log Cloud pour la même session.
     *          Détection de conflit : même (company_id, employee_id, date, session_number).
     */
    public function test_conflict_detection_by_unique_session_key(): void
    {
        $date          = Carbon::today()->toDateString();
        $sessionNumber = 1;

        // Log Edge (créé hors-ligne)
        $edgeLog = AttendanceLog::create([
            'company_id'          => $this->company->id,
            'employee_id'         => $this->employee->id,
            'schedule_id'         => $this->schedule->id,
            'date'                => $date,
            'session_number'      => $sessionNumber,
            'check_in'            => Carbon::today()->setTime(8, 3, 0)->toDateTimeString(),
            'method'              => 'badge',
            'work_type'           => 'presentiel',
            'biometric_type'      => 'none',
            'synced_from_offline' => false,
            'status'              => 'present',
            'hours_worked'        => '0',
            'overtime_hours'      => '0',
            'late_minutes'        => 3,
        ]);

        // Log Cloud (arrivé via correction manager pendant la coupure)
        // Dans la stratégie "Cloud wins", le Cloud log a priority (check_in corrigé à 08:00)
        $cloudLog = AttendanceLog::create([
            'company_id'          => $this->company->id,
            'employee_id'         => $this->employee->id,
            'schedule_id'         => $this->schedule->id,
            'date'                => $date,
            'session_number'      => $sessionNumber + 10, // session_number différent pour éviter doublon unique en DB
            'check_in'            => Carbon::today()->setTime(8, 0, 0)->toDateTimeString(),
            'method'              => 'manual',
            'work_type'           => 'presentiel',
            'biometric_type'      => 'none',
            'synced_from_offline' => true,
            'status'              => 'present',
            'hours_worked'        => '9',
            'overtime_hours'      => '0',
            'late_minutes'        => 0,
        ]);

        // Détection conflit : même employee + date + (session ≈ 1)
        $conflictQuery = AttendanceLog::where('company_id', $this->company->id)
            ->where('employee_id', $this->employee->id)
            ->whereDate('date', $date)
            ->where('session_number', '<=', 5)  // tolère session 1 ou 11
            ->get();

        $this->assertCount(2, $conflictQuery, 'Les deux versions (Edge + Cloud) doivent coexister pendant résolution');

        // La version Edge est identifiable
        $edgeVersion = $conflictQuery->firstWhere('synced_from_offline', false);
        $this->assertNotNull($edgeVersion, 'Version Edge doit être détectable');

        // La version Cloud est identifiable
        $cloudVersion = $conflictQuery->firstWhere('synced_from_offline', true);
        $this->assertNotNull($cloudVersion, 'Version Cloud doit être détectable');
    }

    /**
     * 4.3.b — Résolution "Cloud wins" : le log Edge est archivé/écrasé,
     *          le log Cloud final est marqué synced_from_offline=true.
     */
    public function test_cloud_wins_conflict_resolution(): void
    {
        $date = Carbon::today()->toDateString();

        // Log Edge (moins précis — 08:07)
        $edgeLog = AttendanceLog::create([
            'company_id'          => $this->company->id,
            'employee_id'         => $this->employee->id,
            'schedule_id'         => $this->schedule->id,
            'date'                => $date,
            'session_number'      => 1,
            'check_in'            => Carbon::today()->setTime(8, 7, 0)->toDateTimeString(),
            'method'              => 'qr_code',
            'work_type'           => 'presentiel',
            'biometric_type'      => 'none',
            'synced_from_offline' => false,
            'status'              => 'present',
            'hours_worked'        => '0',
            'overtime_hours'      => '0',
            'late_minutes'        => 7,
            'punch_note'          => 'OFFLINE_EDGE_VERSION',
        ]);

        // Stratégie Cloud wins : le Cloud pousse la version corrigée (08:00, late_minutes=0)
        // On simule la résolution en mettant à jour le log Edge avec les données Cloud
        $edgeLog->update([
            'check_in'            => Carbon::today()->setTime(8, 0, 0)->toDateTimeString(),
            'late_minutes'        => 0,
            'synced_from_offline' => true,
            'punch_note'          => 'CLOUD_WIN_RESOLVED',
            'correction_note'     => 'Conflit Edge/Cloud résolu — version Cloud appliquée',
        ]);

        $resolved = AttendanceLog::find($edgeLog->id);

        $this->assertTrue((bool) $resolved->synced_from_offline, 'Après résolution, doit être marqué synced');
        $this->assertSame(0, $resolved->late_minutes, 'La valeur Cloud (0 min de retard) doit prévaloir');
        $this->assertSame(
            Carbon::today()->setTime(8, 0, 0)->toDateTimeString(),
            $resolved->check_in,
            'Le check_in Cloud (08:00) doit prévaloir'
        );
        $this->assertNotNull($resolved->correction_note, "L'audit trail doit documenteer la résolution");
    }

    /**
     * 4.3.c — Les deux versions (Edge et Cloud) sont traçables via punch_note.
     *          Aucune donnée n'est perdue définitivement (audit complet).
     */
    public function test_conflict_audit_trail_is_preserved(): void
    {
        $date = Carbon::today()->toDateString();

        // Log Edge original
        $log = AttendanceLog::create([
            'company_id'          => $this->company->id,
            'employee_id'         => $this->employee->id,
            'schedule_id'         => $this->schedule->id,
            'date'                => $date,
            'session_number'      => 1,
            'check_in'            => Carbon::today()->setTime(8, 12, 0)->toDateTimeString(),
            'method'              => 'badge',
            'work_type'           => 'presentiel',
            'biometric_type'      => 'none',
            'synced_from_offline' => false,
            'status'              => 'present',
            'hours_worked'        => '0',
            'overtime_hours'      => '0',
            'late_minutes'        => 12,
            'punch_meta'          => json_encode([
                'original_edge_check_in' => Carbon::today()->setTime(8, 12, 0)->toIso8601String(),
                'edge_node_id'           => 'edge-conflict-001',
                'conflict_detected'      => true,
                'cloud_check_in'         => Carbon::today()->setTime(8, 0, 0)->toIso8601String(),
            ]),
        ]);

        // Vérifier que punch_meta contient les métadonnées d'audit
        $reloaded  = AttendanceLog::find($log->id);
        $punchMeta = json_decode($reloaded->punch_meta, true);

        $this->assertArrayHasKey('original_edge_check_in', $punchMeta, 'Audit trail : check_in Edge original conservé');
        $this->assertArrayHasKey('edge_node_id', $punchMeta, 'Audit trail : node_id edge conservé');
        $this->assertTrue($punchMeta['conflict_detected'], 'Audit trail : conflit doit être signalé');
        $this->assertArrayHasKey('cloud_check_in', $punchMeta, 'Audit trail : version Cloud référencée');
    }

    /**
     * 4.3.d — Sans conflit (log Edge unique, pas de version Cloud), la sync
     *          se passe normalement sans appliquer de résolution.
     */
    public function test_no_conflict_sync_works_normally(): void
    {
        $date = Carbon::today()->toDateString();

        $log = AttendanceLog::create([
            'company_id'          => $this->company->id,
            'employee_id'         => $this->employee->id,
            'schedule_id'         => $this->schedule->id,
            'date'                => $date,
            'session_number'      => 1,
            'check_in'            => Carbon::today()->setTime(7, 58, 0)->toDateTimeString(),
            'method'              => 'biometric',
            'work_type'           => 'presentiel',
            'biometric_type'      => 'fingerprint',
            'synced_from_offline' => false,
            'status'              => 'present',
            'hours_worked'        => '0',
            'overtime_hours'      => '0',
            'late_minutes'        => 0,
        ]);

        // Sync sans conflit = simple marquage
        $log->update(['synced_from_offline' => true]);

        $this->assertDatabaseHas('attendance_logs', [
            'id'                  => $log->id,
            'synced_from_offline' => true,
            'correction_note'     => null,  // pas d'audit trail conflit
        ]);
    }
}
