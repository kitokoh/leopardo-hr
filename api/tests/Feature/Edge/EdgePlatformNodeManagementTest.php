<?php

declare(strict_types=1);

namespace Tests\Feature\Edge;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use App\Modules\EdgeSync\Domain\Models\EdgeLicense;
use App\Modules\EdgeSync\Domain\Models\EdgeNode;
use App\Modules\EdgeSync\Domain\Models\SyncQueue;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * Regression coverage for issue #1291.
 *
 * The `/platform/edge/nodes*` super-admin endpoints
 * (EdgeController::listNodes/forceSync/revokeNode) used to run raw
 * `DB::table('edge_nodes')` queries against a legacy bigint schema
 * (node_id/pending_count/license_valid columns) that is never actually
 * created in production/CI — only the canonical UUID/DDD `edge_nodes`
 * schema (App\Modules\EdgeSync\Domain\Models\EdgeNode) is. These tests
 * exercise the endpoints against that canonical schema to make sure
 * they keep working against the real table shape going forward.
 */
class EdgePlatformNodeManagementTest extends TestCase
{
    use CreatesMvpSchema;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();

        $this->company = Company::factory()->create([
            'schema_name'  => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status'       => 'active',
        ]);
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    private function superAdmin(): SuperAdmin
    {
        return SuperAdmin::query()->create([
            'name'          => 'Platform Admin',
            'email'         => fake()->unique()->safeEmail(),
            'password_hash' => Hash::make('password123'),
        ]);
    }

    private function actingAsSuperAdmin(): void
    {
        Sanctum::actingAs($this->superAdmin(), ['*'], 'super_admin_api');
    }

    private function createEdgeNode(array $overrides = []): EdgeNode
    {
        return EdgeNode::create(array_merge([
            'company_id'    => $this->company->id,
            'name'          => 'Kiosque Entrée',
            'slug'          => 'kiosque-entree-'.fake()->unique()->numberBetween(1000, 999999),
            'status'        => 'active',
            'mode'          => 'hybrid',
            'last_seen_at'  => Carbon::now(),
            'public_ip'     => '203.0.113.10',
            'edge_version'  => '1.2.3',
            'metadata'      => [],
            'capabilities'  => [],
        ], $overrides));
    }

    /**
     * GET /platform/edge/nodes must read the canonical EdgeNode schema,
     * not a raw legacy `edge_nodes` bigint table.
     */
    public function test_list_nodes_returns_data_from_canonical_schema(): void
    {
        $this->actingAsSuperAdmin();

        $node = $this->createEdgeNode(['name' => 'Kiosque Principal']);

        SyncQueue::create([
            'edge_node_id' => $node->id,
            'entity_type'  => 'attendance_log',
            'entity_id'    => (string) fake()->uuid(),
            'operation'    => 'create',
            'payload'      => ['foo' => 'bar'],
            'status'       => 'pending',
        ]);

        EdgeLicense::create([
            'company_id'        => $this->company->id,
            'edge_node_id'      => $node->id,
            'license_key'       => (string) fake()->uuid(),
            'signed_payload'    => 'stub-payload',
            'allowed_features'  => [],
            'max_employees'     => 50,
            'issued_at'         => Carbon::now(),
            'expires_at'        => Carbon::now()->addDays(30),
            'last_validated_at' => Carbon::now(),
            'validation_status' => 'valid',
        ]);

        $response = $this->getJson('/api/v1/platform/edge/nodes')->assertOk();

        $response->assertJsonPath('data.0.id', $node->id);
        $response->assertJsonPath('data.0.node_id', $node->slug);
        $response->assertJsonPath('data.0.name', 'Kiosque Principal');
        $response->assertJsonPath('data.0.pending_count', 1);
        $response->assertJsonPath('data.0.license_valid', true);
        $response->assertJsonPath('data.0.company_name', $this->company->name);
    }

    /**
     * POST /platform/edge/nodes/{uuid}/sync must accept the node UUID
     * (not a numeric legacy id) and mark a sync request against the
     * canonical row.
     */
    public function test_force_sync_accepts_node_uuid_and_records_request(): void
    {
        $this->actingAsSuperAdmin();

        $node = $this->createEdgeNode();

        $this->postJson("/api/v1/platform/edge/nodes/{$node->id}/sync")
            ->assertOk()
            ->assertJsonPath('status', 'sync_requested');

        $node->refresh();
        $this->assertArrayHasKey('sync_requested_at', $node->metadata);
    }

    public function test_force_sync_returns_404_for_unknown_node(): void
    {
        $this->actingAsSuperAdmin();

        $this->postJson('/api/v1/platform/edge/nodes/'.fake()->uuid().'/sync')
            ->assertStatus(404)
            ->assertJsonPath('error', 'not_found');
    }

    /**
     * DELETE /platform/edge/nodes/{uuid} must revoke the canonical
     * EdgeNode row and its associated license, not a legacy table.
     */
    public function test_revoke_node_marks_node_revoked_and_revokes_license(): void
    {
        $this->actingAsSuperAdmin();

        $node = $this->createEdgeNode();

        EdgeLicense::create([
            'company_id'        => $this->company->id,
            'edge_node_id'      => $node->id,
            'license_key'       => (string) fake()->uuid(),
            'signed_payload'    => 'stub-payload',
            'allowed_features'  => [],
            'max_employees'     => 50,
            'issued_at'         => Carbon::now(),
            'expires_at'        => Carbon::now()->addDays(30),
            'last_validated_at' => Carbon::now(),
            'validation_status' => 'valid',
        ]);

        $this->deleteJson("/api/v1/platform/edge/nodes/{$node->id}")
            ->assertOk()
            ->assertJsonPath('status', 'revoked');

        $node->refresh();
        $this->assertSame('revoked', $node->status);

        $license = EdgeLicense::where('edge_node_id', $node->id)->first();
        $this->assertSame('revoked', $license->validation_status);
    }

    /**
     * POST /edge/heartbeat must accept either the node UUID or its
     * human-readable slug and update the canonical row.
     */
    public function test_heartbeat_accepts_uuid_and_updates_canonical_node(): void
    {
        $node = $this->createEdgeNode(['last_seen_at' => Carbon::now()->subHour(), 'status' => 'inactive']);

        $this->postJson('/api/v1/edge/heartbeat', [
            'node_id'       => $node->id,
            'pending_count' => 2,
            'version'       => '9.9.9',
            'ip_address'    => '198.51.100.5',
        ])->assertOk()->assertJsonPath('status', 'ok');

        $node->refresh();
        $this->assertSame('active', $node->status);
        $this->assertSame('9.9.9', $node->edge_version);
        $this->assertSame('198.51.100.5', $node->public_ip);
        $this->assertNotNull($node->last_seen_at);
    }

    public function test_heartbeat_accepts_slug_identifier(): void
    {
        $node = $this->createEdgeNode();

        $this->postJson('/api/v1/edge/heartbeat', [
            'node_id' => $node->slug,
        ])->assertOk()->assertJsonPath('status', 'ok');

        $node->refresh();
        $this->assertSame('active', $node->status);
    }

    public function test_heartbeat_returns_404_for_unknown_node(): void
    {
        $this->postJson('/api/v1/edge/heartbeat', [
            'node_id' => 'does-not-exist',
        ])->assertStatus(404)->assertJsonPath('error', 'node_not_found');
    }
}
