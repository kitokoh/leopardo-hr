<?php

declare(strict_types=1);

namespace Tests\Feature\EdgeSync;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\EdgeSync\Domain\Models\EdgeNode;
use App\Modules\EdgeSync\Domain\Models\SyncQueue;
use App\Modules\EdgeSync\Infrastructure\Services\SyncEngineService;
use Illuminate\Support\Facades\DB;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * #7840 (audit CRITIQUE) — durcissement de SyncEngineService::applyGeneric :
 *
 *   - allowlist stricte des entity_type synchronisables (le nom de table ne
 *     dérive JAMAIS d'une entrée non allowlistée) ;
 *   - forçage du company_id du tenant du nœud sur insert/update (le payload
 *     ne peut pas imposer le sien) ;
 *   - filtre company_id sur update/delete — cross-tenant impossible.
 */
class EdgeSyncApplyGenericSecurityTest extends TestCase
{
    use CreatesMvpSchema;

    private Company $company;

    private Company $otherCompany;

    private EdgeNode $node;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();

        /** @var Company $company */
        $company = Company::factory()->create([
            'slug' => 'acme-secu-7840',
            'status' => 'active',
        ]);
        $this->company = $company;

        /** @var Company $otherCompany */
        $otherCompany = Company::factory()->create([
            'slug' => 'rival-secu-7840',
            'status' => 'active',
        ]);
        $this->otherCompany = $otherCompany;

        /** @var EdgeNode $node */
        $node = EdgeNode::create([
            'company_id' => $this->company->id,
            'name' => 'Site Sécurité',
            'slug' => 'site-secu-7840',
            'status' => 'active',
            'mode' => 'hybrid',
            'edge_version' => '1.0.0',
            'capabilities' => ['features' => ['attendance', 'absence'], 'max_employees' => 100],
            'license_expires_at' => now()->addDays(30),
            'metadata' => ['edge_token' => hash('sha256', 'test-edge-token-7840')],
        ]);
        $this->node = $node;
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    // ── Allowlist ─────────────────────────────────────────

    /** @test */
    public function it_rejects_entity_type_outside_allowlist(): void
    {
        // Attaque : un nœud compromis cible une table du search_path
        // (`companies`) via entity_type — l'item doit être rejeté en conflit
        // sans qu'AUCUNE requête ne touche la table visée.
        $companiesBefore = DB::table('companies')->count();

        $item = SyncQueue::create([
            'edge_node_id' => $this->node->id,
            'entity_type' => 'companies',
            'entity_id' => 'evil-uuid-001',
            'operation' => 'create',
            'payload' => [
                'name' => 'Evil Corp',
                'slug' => 'evil-corp-7840',
                'status' => 'active',
                'updated_at' => now()->toDateTimeString(),
            ],
            'status' => 'pending',
            'attempt_count' => 0,
        ]);

        $result = app(SyncEngineService::class)->push($this->node);

        $item->refresh();
        $this->assertSame('conflict', $item->status);
        $this->assertNotNull($item->conflict_note);
        $this->assertStringContainsString('hors allowlist', (string) $item->conflict_note);
        $this->assertSame(1, $result['conflicts']);
        $this->assertSame(0, $result['sent']);
        $this->assertSame($companiesBefore, DB::table('companies')->count());
    }

    /** @test */
    public function it_rejects_arbitrary_table_names_even_with_delete_operation(): void
    {
        $usersBefore = DB::table('users')->count();

        $item = SyncQueue::create([
            'edge_node_id' => $this->node->id,
            'entity_type' => 'users',
            'entity_id' => '1',
            'operation' => 'delete',
            'payload' => ['updated_at' => now()->toDateTimeString()],
            'status' => 'pending',
            'attempt_count' => 0,
        ]);

        app(SyncEngineService::class)->push($this->node);

        $this->assertSame('conflict', $item->refresh()->status);
        $this->assertSame($usersBefore, DB::table('users')->count());
    }

    // ── Forçage tenant sur insert ─────────────────────────

    /** @test */
    public function generic_create_neutralizes_foreign_company_id_in_payload(): void
    {
        // Payload malveillant : company_id d'un AUTRE tenant — la ligne créée
        // doit être rattachée au tenant du nœud, jamais à celui du payload.
        SyncQueue::create([
            'edge_node_id' => $this->node->id,
            'entity_type' => 'absences_generic',
            'entity_id' => '999901',
            'operation' => 'create',
            'payload' => $this->absencePayload([
                'company_id' => $this->otherCompany->id, // ← étranger, doit être écrasé
            ]),
            'status' => 'pending',
            'attempt_count' => 0,
        ]);

        $result = (new GenericPathSyncEngineService)->push($this->node);

        $this->assertSame(1, $result['sent']);
        $this->assertSame(0, DB::table('absences')
            ->where('company_id', $this->otherCompany->id)
            ->count());
        $this->assertSame(1, DB::table('absences')
            ->where('company_id', $this->company->id)
            ->where('reason', 'secu-7840')
            ->count());
    }

    /** @test */
    public function attendance_create_neutralizes_foreign_company_id_in_payload(): void
    {
        // Même garantie sur le handler dédié attendance_logs.
        SyncQueue::create([
            'edge_node_id' => $this->node->id,
            'entity_type' => 'attendance_logs',
            'entity_id' => 'att-secu-7840',
            'operation' => 'create',
            'payload' => [
                'company_id' => $this->otherCompany->id, // ← étranger, doit être écrasé
                'employee_id' => 1,
                'external_event_id' => 'att-secu-7840',
                'check_in' => now()->subHours(2)->toDateTimeString(),
                'method' => 'mobile',
                'status' => 'present',
                'session_number' => 1,
                'date' => now()->toDateString(),
                'work_type' => 'onsite',
                'biometric_type' => 'none',
                'hours_worked' => '0',
                'overtime_hours' => '0',
                'late_minutes' => 0,
                'gps_lat' => '0',
                'gps_lng' => '0',
                'created_at' => now()->toDateTimeString(),
                'updated_at' => now()->toDateTimeString(),
            ],
            'status' => 'pending',
            'attempt_count' => 0,
        ]);

        $result = app(SyncEngineService::class)->push($this->node);

        $this->assertSame(1, $result['sent']);
        $this->assertSame(0, DB::table('attendance_logs')
            ->where('company_id', $this->otherCompany->id)
            ->count());
        $this->assertSame(1, DB::table('attendance_logs')
            ->where('company_id', $this->company->id)
            ->where('external_event_id', 'att-secu-7840')
            ->count());
    }

    // ── Cross-tenant update/delete ────────────────────────

    /** @test */
    public function generic_update_cannot_touch_another_tenants_record(): void
    {
        $foreignId = $this->insertForeignAbsence();

        SyncQueue::create([
            'edge_node_id' => $this->node->id,
            'entity_type' => 'absences_generic',
            'entity_id' => (string) $foreignId,
            'operation' => 'update',
            'payload' => $this->absencePayload([
                'id' => $foreignId, // clé interdite en update — doit être retirée
                'status' => 'approved',
                'updated_at' => now()->addYear()->toDateTimeString(), // gagne le LWW
            ]),
            'status' => 'pending',
            'attempt_count' => 0,
        ]);

        (new GenericPathSyncEngineService)->push($this->node);

        $foreign = DB::table('absences')->where('id', $foreignId)->first();
        $this->assertNotNull($foreign);
        /** @var object{company_id: string, status: string} $foreign */
        $this->assertSame('pending', $foreign->status);
        $this->assertSame((string) $this->otherCompany->id, (string) $foreign->company_id);
    }

    /** @test */
    public function generic_delete_cannot_touch_another_tenants_record(): void
    {
        $foreignId = $this->insertForeignAbsence();

        SyncQueue::create([
            'edge_node_id' => $this->node->id,
            'entity_type' => 'absences_generic',
            'entity_id' => (string) $foreignId,
            'operation' => 'delete',
            'payload' => ['updated_at' => now()->addYear()->toDateTimeString()],
            'status' => 'pending',
            'attempt_count' => 0,
        ]);

        (new GenericPathSyncEngineService)->push($this->node);

        $this->assertSame(1, DB::table('absences')->where('id', $foreignId)->count());
    }

    // ── Helpers ───────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function absencePayload(array $overrides = []): array
    {
        return array_merge([
            'employee_id' => 1,
            'absence_type_id' => 1,
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
            'status' => 'pending',
            'reason' => 'secu-7840',
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ], $overrides);
    }

    /**
     * Insère une absence appartenant à l'AUTRE tenant et retourne son id.
     */
    private function insertForeignAbsence(): int
    {
        return (int) DB::table('absences')->insertGetId([
            'company_id' => $this->otherCompany->id,
            'employee_id' => 1,
            'absence_type_id' => 1,
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
            'status' => 'pending',
            'created_at' => now()->subYear()->toDateTimeString(),
            'updated_at' => now()->subYear()->toDateTimeString(),
        ]);
    }
}

/**
 * Étend l'allowlist avec un entity_type SANS handler dédié afin d'exercer le
 * chemin applyGeneric de bout en bout via push() (aucune modification de la
 * classe de production — même approche que ExposedSyncEngineService, #4978).
 */
class GenericPathSyncEngineService extends SyncEngineService
{
    /**
     * @return array<string, string>
     */
    protected function syncableEntityTables(): array
    {
        return array_merge(parent::syncableEntityTables(), [
            'absences_generic' => 'absences',
        ]);
    }
}
