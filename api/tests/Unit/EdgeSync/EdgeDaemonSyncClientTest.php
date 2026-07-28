<?php

declare(strict_types=1);

namespace Tests\Unit\EdgeSync;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\EdgeSync\Domain\Models\EdgeNode;
use App\Modules\EdgeSync\Domain\Models\SyncQueue;
use App\Modules\EdgeSync\Infrastructure\Services\EdgeDaemonSyncClient;
use Illuminate\Support\Facades\Http;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * Issue #1286 — the `edge:sync-daemon` command must perform a real HTTP
 * push/pull against the Cloud API instead of writing to whatever database
 * connection happens to be locally configured (which is what
 * SyncEngineService::applyToCloud()/pull() do, correctly, when invoked
 * Cloud-side from EdgeNodeController).
 *
 * These tests exercise EdgeDaemonSyncClient in isolation with Http::fake(),
 * asserting the exact request shape the Cloud's EdgeNodeController expects
 * (POST /api/v1/edge-node/{nodeId}/push, GET .../pull, bearer edge token)
 * and that local sync_queue rows are marked synced/failed based on the
 * Cloud's response rather than being applied to a local table.
 */
class EdgeDaemonSyncClientTest extends TestCase
{
    use CreatesMvpSchema;

    private const NODE_ID = '11111111-1111-1111-1111-111111111111';

    private const EDGE_TOKEN = 'test-edge-token-plaintext';

    private const CLOUD_URL = 'https://api.leopardo.app';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();

        // `sync_queue.edge_node_id` carries a FK to `edge_nodes`, so every
        // test that inserts SyncQueue rows against NODE_ID needs a matching
        // EdgeNode (and its parent Company) to satisfy the constraint —
        // otherwise SQLite raises "FOREIGN KEY constraint failed" before the
        // client under test ever runs.
        $company = Company::factory()->create([
            'slug' => 'edge-daemon-test-co',
            'status' => 'active',
        ]);

        // `id` is not in EdgeNode::$fillable, so a plain create() would
        // silently drop it and let HasUuids generate a random primary key
        // instead of NODE_ID — use forceCreate() to pin the id the rest of
        // this test class relies on.
        EdgeNode::forceCreate([
            'id' => self::NODE_ID,
            'company_id' => $company->id,
            'name' => 'Edge Daemon Test Node',
            'slug' => 'edge-daemon-test-node',
            'status' => 'active',
            'mode' => 'hybrid',
            'edge_version' => '1.0.0',
            'capabilities' => [],
            'license_expires_at' => now()->addDays(30),
            'metadata' => ['edge_token' => hash('sha256', self::EDGE_TOKEN)],
        ]);
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    private function client(): EdgeDaemonSyncClient
    {
        return new EdgeDaemonSyncClient(
            cloudApiUrl: self::CLOUD_URL,
            edgeNodeId: self::NODE_ID,
            edgeToken: self::EDGE_TOKEN,
        );
    }

    public function test_push_posts_pending_queue_items_to_cloud_and_marks_them_synced(): void
    {
        $item = SyncQueue::create([
            'edge_node_id'  => self::NODE_ID,
            'entity_type'   => 'attendance_logs',
            'entity_id'     => '22222222-2222-2222-2222-222222222222',
            'operation'     => 'create',
            'payload'       => ['employee_id' => 'emp-1', 'check_in' => '2026-07-26T08:00:00Z'],
            'status'        => 'pending',
            'attempt_count' => 0,
        ]);

        Http::fake([
            self::CLOUD_URL . '/api/v1/edge-node/' . self::NODE_ID . '/push' => Http::response(['queued' => 1], 200),
        ]);

        $result = $this->client()->push();

        $this->assertSame(['sent' => 1, 'failed' => 0], $result);

        Http::assertSent(function ($request) {
            return $request->url() === self::CLOUD_URL . '/api/v1/edge-node/' . self::NODE_ID . '/push'
                && $request->hasHeader('Authorization', 'Bearer ' . self::EDGE_TOKEN)
                && $request['records'][0]['entity_type'] === 'attendance_logs'
                && $request['records'][0]['entity_id'] === '22222222-2222-2222-2222-222222222222';
        });

        $this->assertSame('synced', $item->refresh()->status);
        $this->assertNotNull($item->synced_at);
    }

    public function test_push_marks_items_pending_for_retry_on_transient_cloud_failure(): void
    {
        $item = SyncQueue::create([
            'edge_node_id'  => self::NODE_ID,
            'entity_type'   => 'absences',
            'entity_id'     => '33333333-3333-3333-3333-333333333333',
            'operation'     => 'create',
            'payload'       => ['employee_id' => 'emp-2'],
            'status'        => 'pending',
            'attempt_count' => 1,
        ]);

        Http::fake([
            self::CLOUD_URL . '/api/v1/edge-node/' . self::NODE_ID . '/push' => Http::response(['error' => 'unavailable'], 503),
        ]);

        $result = $this->client()->push();

        $this->assertSame(['sent' => 0, 'failed' => 1], $result);

        $item->refresh();
        $this->assertSame('pending', $item->status);
        $this->assertSame(2, $item->attempt_count);
    }

    public function test_push_marks_item_failed_after_five_attempts(): void
    {
        $item = SyncQueue::create([
            'edge_node_id'  => self::NODE_ID,
            'entity_type'   => 'absences',
            'entity_id'     => '44444444-4444-4444-4444-444444444444',
            'operation'     => 'create',
            'payload'       => ['employee_id' => 'emp-3'],
            'status'        => 'pending',
            'attempt_count' => 4,
        ]);

        Http::fake([
            self::CLOUD_URL . '/api/v1/edge-node/' . self::NODE_ID . '/push' => Http::response(['error' => 'unavailable'], 503),
        ]);

        $this->client()->push();

        $this->assertSame('failed', $item->refresh()->status);
    }

    public function test_push_is_a_noop_when_queue_is_empty(): void
    {
        Http::fake();

        $result = $this->client()->push();

        $this->assertSame(['sent' => 0, 'failed' => 0], $result);
        Http::assertNothingSent();
    }

    public function test_pull_requests_delta_from_cloud_with_bearer_token(): void
    {
        Http::fake([
            self::CLOUD_URL . '/api/v1/edge-node/' . self::NODE_ID . '/pull' => Http::response([
                'since'    => '2026-07-25T00:00:00Z',
                'entities' => [],
            ], 200),
        ]);

        $result = $this->client()->pull();

        $this->assertSame(['received' => 0], $result);

        Http::assertSent(function ($request) {
            return $request->url() === self::CLOUD_URL . '/api/v1/edge-node/' . self::NODE_ID . '/pull'
                && $request->hasHeader('Authorization', 'Bearer ' . self::EDGE_TOKEN)
                && $request->method() === 'GET';
        });
    }

    public function test_pull_returns_zero_received_on_cloud_failure(): void
    {
        Http::fake([
            self::CLOUD_URL . '/api/v1/edge-node/' . self::NODE_ID . '/pull' => Http::response(['error' => 'invalid token'], 401),
        ]);

        $result = $this->client()->pull();

        $this->assertSame(['received' => 0], $result);
    }
}
