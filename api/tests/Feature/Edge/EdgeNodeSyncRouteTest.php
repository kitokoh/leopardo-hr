<?php

declare(strict_types=1);

namespace Tests\Feature\Edge;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Auth\Domain\Models\Employee;
use App\Modules\EdgeSync\Application\Services\SyncEngineService;
use App\Modules\EdgeSync\Domain\Models\EdgeNode;
use App\Modules\EdgeSync\Domain\Models\SyncLog;
use Illuminate\Support\Facades\DB;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * QA 2026-08-15 (#2654) — POST /api/v1/edge/{nodeId}/sync existait dans les
 * routes mais la méthode `EdgeNodeController::sync()` n'existait pas
 * (BadMethodCallException → 500). Ce test verrouille la route, son scopage
 * tenant et la délégation au moteur de synchronisation.
 */
class EdgeNodeSyncRouteTest extends TestCase
{
    use CreatesMvpSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();
        $this->createEdgeNodesTable();
    }

    protected function tearDown(): void
    {
        DB::statement('DROP TABLE IF EXISTS edge_nodes CASCADE');
        DB::statement('DROP TABLE IF EXISTS sync_logs CASCADE');
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    private function createEdgeNodesTable(): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('edge_nodes')) {
            \Illuminate\Support\Facades\Schema::create('edge_nodes', function ($table): void {
                $table->uuid('id')->primary();
                $table->uuid('company_id')->index();
                $table->string('name');
                $table->string('slug')->unique();
                $table->string('status')->default('active');
                $table->string('mode')->default('hybrid');
                $table->timestamp('last_sync_at')->nullable();
                $table->timestamp('last_seen_at')->nullable();
                $table->json('metadata')->default('{}');
                $table->timestamps();
            });
        }

        if (! \Illuminate\Support\Facades\Schema::hasTable('sync_logs')) {
            \Illuminate\Support\Facades\Schema::create('sync_logs', function ($table): void {
                $table->uuid('id')->primary();
                $table->uuid('edge_node_id')->index();
                $table->string('direction');
                $table->string('status');
                $table->integer('records_sent')->default(0);
                $table->integer('records_received')->default(0);
                $table->integer('conflicts_detected')->default(0);
                $table->integer('conflicts_resolved')->default(0);
                $table->text('error_message')->nullable();
                $table->json('summary')->default('{}');
                $table->timestamp('started_at')->nullable();
                $table->timestamp('finished_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function test_manual_sync_route_works_for_tenant_owner(): void
    {
        $company = Company::factory()->create([
            'slug' => 'sync-owner-'.uniqid(),
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
        ]);

        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'status' => 'active',
        ]);

        $node = EdgeNode::query()->create([
            'company_id' => $company->id,
            'name' => 'Node Alpha',
            'slug' => 'node-alpha-'.uniqid(),
            'status' => 'active',
        ]);

        $log = SyncLog::query()->create([
            'edge_node_id' => $node->id,
            'direction' => 'bidirectional',
            'status' => 'success',
            'started_at' => now(),
            'finished_at' => now(),
        ]);

        $fake = \Mockery::mock(SyncEngineService::class);
        $fake->shouldReceive('sync')->once()->with(\Mockery::on(
            fn (EdgeNode $n): bool => $n->id === $node->id
        ))->andReturn($log);
        $this->app->instance(SyncEngineService::class, $fake);

        $response = $this->actingAs($employee)->postJson("/api/v1/edge/{$node->id}/sync");

        $response->assertOk();
        $response->assertJsonPath('data.status', 'success');
        $response->assertJsonPath('node.id', $node->id);
    }

    public function test_manual_sync_is_tenant_scoped(): void
    {
        $companyA = Company::factory()->create([
            'slug' => 'sync-a-'.uniqid(),
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
        ]);

        $companyB = Company::factory()->create([
            'slug' => 'sync-b-'.uniqid(),
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
        ]);

        $employeeB = Employee::factory()->create([
            'company_id' => $companyB->id,
            'role' => 'manager',
            'status' => 'active',
        ]);

        $nodeA = EdgeNode::query()->create([
            'company_id' => $companyA->id,
            'name' => 'Node A',
            'slug' => 'node-a-'.uniqid(),
            'status' => 'active',
        ]);

        $fake = \Mockery::mock(SyncEngineService::class);
        $fake->shouldNotReceive('sync');
        $this->app->instance(SyncEngineService::class, $fake);

        // Un manager du tenant B ne doit pas pouvoir déclencher le sync
        // d'un nœud du tenant A (404, pas de fuite d'existence).
        $this->actingAs($employeeB)
            ->postJson("/api/v1/edge/{$nodeA->id}/sync")
            ->assertNotFound();
    }
}
