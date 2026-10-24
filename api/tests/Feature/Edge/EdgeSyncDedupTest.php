<?php

declare(strict_types=1);

namespace Tests\Feature\Edge;

use App\Modules\EdgeSync\Application\Actions\PushEdgeRecords;
// #7452 — la classe vit dans Infrastructure\Services (l'import Application\Services ne résout pas).
use App\Modules\EdgeSync\Domain\Models\EdgeNode;
use App\Modules\EdgeSync\Domain\Models\SyncQueue;
use App\Modules\EdgeSync\Infrastructure\Services\EdgeDaemonSyncClient;
use App\Modules\EdgeSync\Infrastructure\Services\SyncEngineService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * #6554 (audit fiabilité M9/M10) — durcissement EdgeSync :
 *  - PushEdgeRecords : transaction + clé de dédup (index unique) — un rejeu
 *    ou un double push concurrent ne crée jamais de doublon dans sync_queue ;
 *  - SyncEngineService : claim conditionnel (WHERE status='pending') — un
 *    item déjà réclamé par un autre process n'est pas traité deux fois ;
 *  - EdgeDaemonSyncClient : un 2xx n'implique plus que TOUT le lot est
 *    accepté — résultats par enregistrement, items rejetés retentés.
 */
class EdgeSyncDedupTest extends TestCase
{
    use CreatesMvpSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();
    }

    protected function tearDown(): void
    {
        // #8396 — la fixture canonique (CreatesMvpSchema) est PARTAGÉE par
        // toutes les classes du worker : on ne droppe ni ne recrée
        // `edge_nodes` / `sync_queue` ici. La version précédente créait une
        // table `edge_nodes` RÉDUITE (10 colonnes, sans `edge_version`) dès
        // que la canonique était absente, puis la laissait en place — les
        // classes `EdgeSync\*` suivantes du même worker inséraient alors un
        // `EdgeNode` avec `edge_version` et échouaient en `SQLSTATE[42703]`.
        // On rend la main à tearDownMvpSchema() (restaure le search_path et
        // invalide le marqueur de fixture) ; l'isolation des données est
        // assurée par le TRUNCATE de la fixture.
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    /**
     * #8396 — garde de non-régression : la table `edge_nodes` construite pour
     * ce test doit porter le schéma CANONIQUE de la migration tenant, et non
     * un sous-ensemble. Sans cette assertion, une fixture réduite peut être
     * réintroduite et casser silencieusement les classes suivantes du worker.
     */
    public function test_edge_nodes_fixture_matches_canonical_schema(): void
    {
        foreach (['edge_version', 'capabilities', 'license_expires_at', 'site_address', 'license_key'] as $column) {
            $this->assertTrue(
                Schema::hasColumn('edge_nodes', $column),
                "La fixture `edge_nodes` doit porter la colonne canonique `{$column}` (#8396).",
            );
        }

        $node = $this->makeNode();
        $this->assertSame('1.0.0', $node->fresh()?->edge_version, '`edge_version` doit faire l\'aller-retour en base.');
    }

    private function makeNode(): EdgeNode
    {
        return EdgeNode::query()->create([
            'company_id' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'Node Dedup',
            'slug' => 'node-dedup-'.uniqid(),
            'status' => 'active',
        ]);
    }

    public function test_push_twice_same_record_does_not_duplicate_queue(): void
    {
        $node = $this->makeNode();
        $record = [
            'entity_type' => 'attendance_logs',
            'entity_id' => 'emp-1',
            'operation' => 'create',
            'payload' => ['checked_in_at' => '2026-09-01T08:00:00Z'],
        ];

        $action = new PushEdgeRecords;

        $first = $action->execute($node, [$record]);
        $second = $action->execute($node, [$record]);

        $this->assertSame(1, $first['queued']);
        $this->assertSame('queued', $first['results'][0]['status']);
        $this->assertSame(0, $second['queued']);
        $this->assertSame('duplicate', $second['results'][0]['status']);

        $this->assertSame(1, SyncQueue::query()->count(), 'Un rejeu ne doit pas dupliquer la file');
    }

    public function test_push_same_payload_different_entity_are_both_queued(): void
    {
        $node = $this->makeNode();
        $action = new PushEdgeRecords;

        $result = $action->execute($node, [
            ['entity_type' => 'attendance_logs', 'entity_id' => 'emp-1', 'operation' => 'create', 'payload' => ['day' => '2026-09-01']],
            ['entity_type' => 'attendance_logs', 'entity_id' => 'emp-2', 'operation' => 'create', 'payload' => ['day' => '2026-09-01']],
        ]);

        $this->assertSame(2, $result['queued']);
        $this->assertSame(2, SyncQueue::query()->count());
    }

    public function test_engine_claim_is_conditional_and_single_processing(): void
    {
        $node = $this->makeNode();

        $itemA = SyncQueue::query()->create([
            'edge_node_id' => $node->id,
            'entity_type' => 'attendance_logs',
            'entity_id' => 'emp-a',
            'operation' => 'create',
            'payload' => ['day' => '2026-09-01'],
            'status' => 'pending',
            'attempt_count' => 0,
        ]);
        $itemB = SyncQueue::query()->create([
            'edge_node_id' => $node->id,
            'entity_type' => 'attendance_logs',
            'entity_id' => 'emp-b',
            'operation' => 'create',
            'payload' => ['day' => '2026-09-01'],
            'status' => 'pending',
            'attempt_count' => 0,
        ]);

        // Première passe : les deux items sont réclamés et traités.
        $engine = $this->createPartialMock(SyncEngineService::class, ['applyToCloud']);
        $engine->method('applyToCloud')->willReturn(['conflict' => false]);
        $engine->push($node);

        $this->assertSame('synced', $itemA->fresh()?->status);
        $this->assertSame('synced', $itemB->fresh()?->status);

        // Seconde passe : plus rien à traiter — applyToCloud ne doit PAS être
        // rappelé (le compteur de la première passe reste à 2).
        $engine2 = $this->createPartialMock(SyncEngineService::class, ['applyToCloud']);
        $engine2->expects($this->never())->method('applyToCloud');
        $engine2->push($node);

        $this->assertSame(2, SyncQueue::query()->where('status', 'synced')->count());
    }

    public function test_atomic_claim_prevents_double_claim(): void
    {
        $node = $this->makeNode();

        $item = SyncQueue::query()->create([
            'edge_node_id' => $node->id,
            'entity_type' => 'attendance_logs',
            'entity_id' => 'emp-x',
            'operation' => 'create',
            'payload' => ['day' => '2026-09-01'],
            'status' => 'pending',
            'attempt_count' => 0,
        ]);

        // Deux "process" concurrents tentent le même claim conditionnel :
        // seul le premier gagne (1 ligne affectée), le second voit 0.
        $first = SyncQueue::query()->whereKey($item->id)->where('status', 'pending')
            ->update(['status' => 'processing', 'attempt_count' => 1]);
        $second = SyncQueue::query()->whereKey($item->id)->where('status', 'pending')
            ->update(['status' => 'processing', 'attempt_count' => 1]);

        $this->assertSame(1, $first);
        $this->assertSame(0, $second);
        $this->assertSame('processing', $item->fresh()?->status);
        $this->assertSame(1, $item->fresh()?->attempt_count);
    }

    public function test_daemon_marks_synced_only_accepted_records_and_retries_rejected(): void
    {
        $node = $this->makeNode();

        SyncQueue::query()->create([
            'edge_node_id' => $node->id,
            'entity_type' => 'attendance_logs',
            'entity_id' => 'rec-ok',
            'operation' => 'create',
            'payload' => ['day' => '2026-09-01'],
            'status' => 'pending',
            'attempt_count' => 0,
        ]);
        SyncQueue::query()->create([
            'edge_node_id' => $node->id,
            'entity_type' => 'attendance_logs',
            'entity_id' => 'rec-ko',
            'operation' => 'create',
            'payload' => ['day' => '2026-09-01'],
            'status' => 'pending',
            'attempt_count' => 0,
        ]);

        Http::fake([
            '*/api/v1/edge-node/*/push' => Http::response([
                'queued' => 1,
                'results' => [
                    ['entity_type' => 'attendance_logs', 'entity_id' => 'rec-ok', 'operation' => 'create', 'status' => 'queued'],
                    ['entity_type' => 'attendance_logs', 'entity_id' => 'rec-ko', 'operation' => 'create', 'status' => 'invalid'],
                ],
            ], 200),
        ]);

        $client = new EdgeDaemonSyncClient(
            cloudApiUrl: 'https://cloud.test',
            edgeNodeId: $node->id,
            edgeToken: 'edge-token',
        );

        $result = $client->push();

        $this->assertSame(1, $result['sent']);
        $this->assertSame(1, $result['failed']);

        $ok = SyncQueue::query()->where('entity_id', 'rec-ok')->first();
        $ko = SyncQueue::query()->where('entity_id', 'rec-ko')->first();
        $this->assertNotNull($ok);
        $this->assertNotNull($ko);

        $this->assertSame('synced', $ok->status);
        $this->assertNotNull($ok->synced_at);
        $this->assertSame('pending', $ko->status, 'Item rejeté par le Cloud → retenté au cycle suivant');
        $this->assertSame(1, $ko->attempt_count);
    }
}
