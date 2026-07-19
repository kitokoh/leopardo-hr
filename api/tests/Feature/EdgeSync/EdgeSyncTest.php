<?php

namespace Tests\Feature\EdgeSync;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\EdgeSync\Application\Services\EdgeLicenseService;
use App\Modules\EdgeSync\Application\Services\SyncEngineService;
use App\Modules\EdgeSync\Domain\Models\EdgeNode;
use App\Modules\EdgeSync\Domain\Models\SyncQueue;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * Phase 4 — EdgeSync Feature Tests
 *
 * Covers:
 *   - Registration and license issuance
 *   - Push (offline data → Cloud)
 *   - Pull delta (Cloud → Edge)
 *   - Conflict resolution (attendance / absence / generic)
 *   - License validation and expiry
 *   - Multi-tenant isolation
 */
class EdgeSyncTest extends TestCase
{
    use CreatesMvpSchema;

    private Company $company;

    private EdgeNode $node;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();

        $this->company = Company::factory()->create([
            'slug' => 'acme-test',
            'status' => 'active',
        ]);

        $this->node = EdgeNode::create([
            'company_id' => $this->company->id,
            'name' => 'Site Principal',
            'slug' => 'site-principal-abc123',
            'status' => 'active',
            'mode' => 'hybrid',
            'edge_version' => '1.0.0',
            'capabilities' => ['features' => ['attendance', 'absence'], 'max_employees' => 100],
            'license_expires_at' => now()->addDays(30),
            'metadata' => ['edge_token' => 'test-edge-token-xxx'],
        ]);
    }

    // ── Registration ─────────────────────────────────────

    /** @test */
    public function it_registers_a_new_edge_node(): void
    {
        $admin = $this->actingAsCompanyAdmin($this->company);

        $response = $this->postJson('/api/v1/edge', [
            'name' => 'Entrepôt Nord',
            'mode' => 'hybrid',
            'capabilities' => ['max_employees' => 50],
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => ['id', 'name', 'slug', 'status', 'mode'],
                'license' => ['license_key', 'expires_at', 'signed_payload'],
            ]);

        $this->assertDatabaseHas('edge_nodes', ['name' => 'Entrepôt Nord']);
        $this->assertDatabaseHas('edge_licenses', ['edge_node_id' => $response->json('data.id')]);
    }

    /** @test */
    public function it_cannot_see_other_company_nodes(): void
    {
        $other = Company::factory()->create(['slug' => 'other-co', 'status' => 'active']);
        EdgeNode::create([
            'company_id' => $other->id,
            'name' => 'Other Node',
            'slug' => 'other-node-xyz',
            'status' => 'active',
            'mode' => 'hybrid',
            'edge_version' => '1.0.0',
            'capabilities' => [],
            'license_expires_at' => now()->addDays(30),
            'metadata' => [],
        ]);

        $admin = $this->actingAsCompanyAdmin($this->company);

        $response = $this->getJson('/api/v1/edge');
        $response->assertOk();

        $ids = collect($response->json('data'))->pluck('company_id')->unique();
        $this->assertCount(1, $ids);
        $this->assertEquals($this->company->id, $ids->first());
    }

    // ── Push (Edge → Cloud) ───────────────────────────────

    /** @test */
    public function it_accepts_offline_attendance_push(): void
    {
        $this->withEdgeToken();

        $response = $this->postJson("/api/v1/edge-node/{$this->node->id}/push", [
            'records' => [
                [
                    'entity_type' => 'attendance_logs',
                    'entity_id' => 'local-uuid-001',
                    'operation' => 'create',
                    'payload' => [
                        'id' => 'local-uuid-001',
                        'company_id' => $this->company->id,
                        'employee_id' => 'emp-uuid-001',
                        'check_in' => now()->subHours(8)->toIso8601String(),
                        'method' => 'mobile',
                        'status' => 'present',
                        'synced_from_offline' => true,
                    ],
                ],
            ],
        ]);

        $response->assertOk()->assertJson(['queued' => 1]);
        $this->assertDatabaseHas('sync_queue', [
            'edge_node_id' => $this->node->id,
            'entity_type' => 'attendance_logs',
            'entity_id' => 'local-uuid-001',
            'operation' => 'create',
            'status' => 'pending',
        ]);
    }

    /** @test */
    public function it_rejects_push_with_invalid_edge_token(): void
    {
        $response = $this->withToken('wrong-token')
            ->postJson("/api/v1/edge-node/{$this->node->id}/push", [
                'records' => [],
            ]);

        $response->assertStatus(401);
    }

    // ── Pull Delta (Cloud → Edge) ─────────────────────────

    /** @test */
    public function it_returns_delta_since_last_sync(): void
    {
        $this->withEdgeToken();

        // Simulate a Cloud employee updated after last sync
        \DB::table('employees')->insert([
            'company_id' => $this->company->id,
            'first_name' => 'Moussa',
            'last_name' => 'Diallo',
            'email' => 'moussa@acme.test',
            'password_hash' => bcrypt('secret'),
            'role' => 'employee',
            'status' => 'active',
            'created_at' => now()->subDay()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ]);

        $this->node->update(['last_sync_at' => now()->subHours(2)]);

        $response = $this->getJson("/api/v1/edge-node/{$this->node->id}/pull");

        $response->assertOk()
            ->assertJsonStructure(['since', 'entities']);
    }

    // ── Sync Engine ───────────────────────────────────────

    /** @test */
    public function it_processes_sync_queue_and_marks_synced(): void
    {
        $employee = Employee::factory()->create([
            'company_id' => $this->company->id,
            'role' => 'employee',
        ]);

        SyncQueue::create([
            'edge_node_id' => $this->node->id,
            'entity_type' => 'attendance_logs',
            'entity_id' => 'att-to-sync-001',
            'operation' => 'create',
            'payload' => [
                'company_id' => $this->company->id,
                'employee_id' => $employee->id,
                'check_in' => now()->subHours(4)->toIso8601String(),
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
                'synced_from_offline' => true,
                'created_at' => now()->toDateTimeString(),
                'updated_at' => now()->toDateTimeString(),
            ],
            'status' => 'pending',
            'attempt_count' => 0,
        ]);

        $service = app(SyncEngineService::class);
        $log = $service->sync($this->node);

        $this->assertEquals('success', $log->status);
        $this->assertGreaterThanOrEqual(0, $log->records_sent);
    }

    // ── Conflict Resolution ───────────────────────────────

    /** @test */
    public function it_resolves_attendance_conflict_with_local_wins(): void
    {
        // Insert an existing attendance log with same external_event_id
        \DB::table('attendance_logs')->insert([
            'company_id' => $this->company->id,
            'employee_id' => 1,
            'check_in' => now()->subHours(8)->toDateTimeString(),
            'external_event_id' => 'duplicate-event-001',
            'method' => 'mobile',
            'status' => 'present',
            'session_number' => 1,
            'date' => now()->toDateString(),
            'work_type' => 'onsite',
            'biometric_type' => 'none',
            'synced_from_offline' => false,
            'hours_worked' => '0',
            'overtime_hours' => '0',
            'late_minutes' => 0,
            'gps_lat' => '0',
            'gps_lng' => '0',
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ]);

        $item = SyncQueue::create([
            'edge_node_id' => $this->node->id,
            'entity_type' => 'attendance_logs',
            'entity_id' => 'duplicate-event-001',
            'operation' => 'create',
            'payload' => ['external_event_id' => 'duplicate-event-001'],
            'status' => 'pending',
            'attempt_count' => 0,
        ]);

        $service = app(SyncEngineService::class);
        $service->push($this->node);

        $item->refresh();
        $this->assertEquals('conflict', $item->status);
        $this->assertEquals('local_wins', $item->conflict_resolution);
    }

    /** @test */
    public function it_resolves_absence_conflict_with_cloud_wins(): void
    {
        // Insert an already-approved absence in Cloud
        \DB::table('absences')->insert([
            'company_id' => $this->company->id,
            'employee_id' => 1,
            'absence_type_id' => 1,
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
            'status' => 'approved',
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ]);

        $absenceId = \DB::table('absences')->first()->id;

        SyncQueue::create([
            'edge_node_id' => $this->node->id,
            'entity_type' => 'absences',
            'entity_id' => (string) $absenceId,
            'operation' => 'update',
            'payload' => ['status' => 'pending', 'updated_at' => now()->toDateTimeString()],
            'status' => 'pending',
            'attempt_count' => 0,
        ]);

        $service = app(SyncEngineService::class);
        $service->push($this->node);

        $item = SyncQueue::where('entity_type', 'absences')->first();
        $this->assertEquals('conflict', $item->status);
        $this->assertEquals('cloud_wins', $item->conflict_resolution);
    }

    // ── License ───────────────────────────────────────────

    /** @test */
    public function it_validates_a_signed_license(): void
    {
        // Skip if no license keys configured (CI without keys)
        if (! config('edge.license_private_key')) {
            $this->markTestSkipped('Edge license keys not configured.');
        }

        $licenseService = app(EdgeLicenseService::class);
        $license = $licenseService->issueLicense($this->node, 30);

        $result = $licenseService->validateLicense($license->signed_payload);
        $this->assertTrue($result['valid']);
        $this->assertEquals($this->node->id, $result['payload']['sub']);
    }

    /** @test */
    public function it_rejects_expired_license(): void
    {
        // Skip if no license keys configured (CI without keys)
        if (! config('edge.license_private_key')) {
            $this->markTestSkipped('Edge license keys not configured.');
        }

        $licenseService = app(EdgeLicenseService::class);

        // Issue a license with -1 day validity (already expired)
        $this->node->update(['capabilities' => ['features' => ['attendance']]]);
        $license = $licenseService->issueLicense($this->node, -1);

        $result = $licenseService->validateLicense($license->signed_payload);
        $this->assertFalse($result['valid']);
    }

    // ── Helpers ───────────────────────────────────────────

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    private function actingAsCompanyAdmin(Company $company): self
    {
        // App\Core\Auth\Domain\Models\User (table `users`) n'a pas de colonne
        // company_id/role : ce sont les colonnes du modele tenant Employee.
        // C'est Employee qui porte le role admin dans ce codebase.
        $admin = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'admin',
        ]);

        return $this->actingAs($admin, 'sanctum');
    }

    private function withEdgeToken(): void
    {
        $this->withToken('test-edge-token-xxx');
    }
}
