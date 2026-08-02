<?php

declare(strict_types=1);

namespace Tests\Feature\EdgeSync;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\EdgeSync\Domain\Models\EdgeNode;
use App\Modules\EdgeSync\Domain\Models\SyncLog;
use App\Modules\EdgeSync\Domain\Models\SyncQueue;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * Real, no-fakes end-to-end integration test: an actual "Cloud API" process
 * (php artisan serve) and an actual "Edge Docker" process (the edge:sync-
 * daemon artisan command run with --once) are started as two independent OS
 * processes and talk to each other over real HTTP, exactly like the real
 * Edge Docker container talks to the real Cloud API in production.
 *
 * This closes the gap described in issue #1296: every other Edge sync test
 * (EdgeDaemonSyncClientTest, EdgeSyncTest, EdgeOfflineScenarioTest) only
 * exercises PHP objects in-process, or fakes the HTTP client — none of them
 * actually spin up two processes and let them talk over a socket.
 */
class EdgeSyncDaemonIntegrationTest extends TestCase
{
    use CreatesMvpSchema;

    private ?Process $serverProcess = null;

    protected function tearDown(): void
    {
        if ($this->serverProcess !== null && $this->serverProcess->isRunning()) {
            $this->serverProcess->stop(5);
        }

        parent::tearDown();
    }

    /**
     * @group integration
     * @group slow
     */
    public function test_real_edge_sync_daemon_process_pushes_queued_records_over_real_http_to_a_real_cloud_server(): void
    {
        if (env('CI') && ! env('RUN_INTEGRATION_TESTS')) {
            $this->markTestSkipped('EdgeSync integration test skipped in CI (requires RUN_INTEGRATION_TESTS=true).');
        }

        $this->setUpMvpSchema();

        $company = Company::factory()->create([
            'slug' => 'edge-daemon-integration-co',
            'status' => 'active',
        ]);

        $edgeToken = 'integration-edge-token-' . Str::random(16);
        $node = EdgeNode::forceCreate([
            'id' => (string) Str::uuid(),
            'company_id' => $company->id,
            'name' => 'Edge Daemon Integration Node',
            'slug' => 'edge-daemon-integration-node',
            'status' => 'active',
            'mode' => 'hybrid',
            'edge_version' => '1.0.0',
            'capabilities' => [],
            'license_expires_at' => now()->addDays(30),
            'metadata' => ['edge_token' => hash('sha256', $edgeToken)],
        ]);

        $queueItem = SyncQueue::create([
            'edge_node_id' => $node->id,
            'entity_type' => 'attendance_logs',
            'entity_id' => (string) Str::uuid(),
            'operation' => 'create',
            'payload' => [
                'employee_id' => (string) Str::uuid(),
                'company_id' => $company->id,
                'check_in' => now()->toIso8601String(),
            ],
            'status' => 'pending',
        ]);

        $port = 18000 + random_int(0, 1500);
        $baseUrl = "http://127.0.0.1:{$port}";

        $this->serverProcess = new Process(
            ['php', 'artisan', 'serve', '--host=127.0.0.1', "--port={$port}"],
            base_path(),
            $this->serverEnvironment(),
        );
        $this->serverProcess->start();

        $this->waitForCloudServerToBeReady($baseUrl);

        $daemon = new Process(
            ['php', 'artisan', 'edge:sync-daemon', '--once'],
            base_path(),
            array_merge($this->serverEnvironment(), [
                'EDGE_NODE_ID' => $node->id,
                'EDGE_TOKEN' => $edgeToken,
                'CLOUD_API_URL' => $baseUrl,
                'CLOUD_SYNC_INTERVAL_MINUTES' => '1',
                'EDGE_SYNC_BATCH_SIZE' => '50',
            ]),
            timeout: 30,
        );
        $daemon->run();

        self::assertTrue(
            $daemon->isSuccessful(),
            "edge:sync-daemon --once exited with a non-zero status.\nSTDOUT:\n{$daemon->getOutput()}\nSTDERR:\n{$daemon->getErrorOutput()}",
        );

        $queueItem->refresh();
        self::assertSame(
            'synced',
            $queueItem->status,
            'Expected the sync_queue item pushed by the real Edge daemon process to be marked synced by the real Cloud API process.',
        );

        $log = SyncLog::where('edge_node_id', $node->id)
            ->where('status', 'success')
            ->latest('id')
            ->first();

        self::assertNotNull(
            $log,
            'Expected a successful sync_logs row to be written by the real Cloud API process after the Edge daemon pushed over real HTTP.',
        );
    }

    /**
     * Environment for both the Cloud API server process and the Edge daemon
     * process: the same test database connection as the rest of the suite,
     * so both processes see (and mutate) the exact same rows the test
     * asserts against afterwards.
     */
    private function serverEnvironment(): array
    {
        $env = [
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => (string) config('database.default'),
        ];

        foreach (['DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD', 'DB_SEARCH_PATH'] as $key) {
            $value = env($key);
            if ($value !== null) {
                $env[$key] = (string) $value;
            }
        }

        return $env;
    }

    private function waitForCloudServerToBeReady(string $baseUrl): void
    {
        $deadline = microtime(true) + 15;

        while (microtime(true) < $deadline) {
            $context = stream_context_create(['http' => ['timeout' => 1]]);
            $result = @file_get_contents($baseUrl . '/api/v1/edge/health', false, $context);

            if ($result !== false) {
                return;
            }

            usleep(200_000);
        }

        self::fail('Cloud API test server (php artisan serve) did not become ready in time.');
    }
}
