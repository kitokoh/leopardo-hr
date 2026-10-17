<?php

declare(strict_types=1);

namespace Tests\Feature\EdgeSync;

use App\Modules\EdgeSync\Application\Actions\PushEdgeRecords;
use App\Modules\EdgeSync\Domain\Models\EdgeNode;
use App\Modules\EdgeSync\Domain\Models\SyncQueue;
use App\Modules\EdgeSync\Infrastructure\Services\SyncEngineService;
use Illuminate\Support\Str;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * #8629 (Lot 5) — diagnostic de synchronisation.
 *
 * Ce qui est prouvé ici, et qui ne l'était pas :
 *  1. une clé d'idempotence FOURNIE par le noeud Edge absorbe un rejeu même
 *     quand le payload a changé (horodatage, métadonnée) ;
 *  2. un échec d'application laisse une CAUSE persistée et un horodatage de
 *     tentative — la file devient diagnosticable sans fouiller les logs ;
 *  3. un conflit est horodaté et sa cause conservée.
 */
class EdgeSyncDiagnosticsTest extends TestCase
{
    // #8629 — le schéma canonique (edge_nodes complet + sync_queue de
    // production, avec les colonnes de diagnostic) vient de la fixture MVP
    // partagée. Une fixture locale plus pauvre casserait tous les tests
    // suivants du worker, qui héritent alors d'une table incomplète.
    use CreatesMvpSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();
    }

    private function makeNode(): EdgeNode
    {
        return EdgeNode::query()->create([
            'company_id' => (string) Str::uuid(),
            'name' => 'Node Diag',
            'slug' => 'node-diag-'.uniqid(),
            'status' => 'active',
        ]);
    }

    public function test_la_cle_d_idempotence_absorbe_un_rejeu_dont_le_payload_a_change(): void
    {
        $node = $this->makeNode();
        $action = new PushEdgeRecords;

        $base = [
            'entity_type' => 'attendance_logs',
            'entity_id' => 'emp-42',
            'operation' => 'create',
            'idempotency_key' => 'op-7f3c',
        ];

        // Même opération métier, horodatage différent : sans clé d'idempotence
        // l'empreinte de payload aurait produit deux lignes.
        $first = $action->execute($node, [$base + ['payload' => ['checked_in_at' => '2026-09-01T08:00:00Z']]]);
        $second = $action->execute($node, [$base + ['payload' => ['checked_in_at' => '2026-09-01T08:00:03Z']]]);

        $this->assertSame('queued', $first['results'][0]['status']);
        // L'empreinte de payload diffère (horodatage différent) : le doublon
        // est absorbé par la clé métier, pas par le hash — c'est bien le
        // comportement visé. On compte sur CE nœud : la base canonique est
        // partagée entre tests.
        $this->assertSame(1, SyncQueue::query()->where('edge_node_id', $node->id)->count());
        $this->assertSame(
            'op-7f3c',
            SyncQueue::query()->where('edge_node_id', $node->id)->first()?->idempotency_key
        );
        $this->assertSame(0, $second['queued'], 'Le rejeu porte la même clé : rien de neuf à mettre en file');
    }

    public function test_un_echec_persiste_sa_cause_et_l_horodatage_de_tentative(): void
    {
        $node = $this->makeNode();

        $item = SyncQueue::query()->create([
            'edge_node_id' => $node->id,
            'entity_type' => 'attendance_logs',
            'entity_id' => 'emp-echec',
            'operation' => 'create',
            'payload' => ['day' => '2026-09-02'],
            'status' => 'pending',
            'attempt_count' => 0,
        ]);

        $engine = $this->createPartialMock(SyncEngineService::class, ['applyToCloud']);
        $engine->method('applyToCloud')->willThrowException(new \RuntimeException('colonne inconnue: checked_in_at'));

        $engine->push($node);

        $fresh = $item->fresh();

        $this->assertSame('pending', $fresh?->status, 'Un premier échec reste rejouable');
        $this->assertSame('colonne inconnue: checked_in_at', $fresh?->last_error);
        $this->assertNotNull($fresh?->last_attempt_at, 'L’âge de la file doit être observable');
    }

    public function test_un_conflit_est_horodate_et_motive(): void
    {
        $node = $this->makeNode();

        $item = SyncQueue::query()->create([
            'edge_node_id' => $node->id,
            'entity_type' => 'attendance_logs',
            'entity_id' => 'emp-conflit',
            'operation' => 'create',
            'payload' => ['day' => '2026-09-03'],
            'status' => 'pending',
            'attempt_count' => 0,
        ]);

        $engine = $this->createPartialMock(SyncEngineService::class, ['applyToCloud']);
        $engine->method('applyToCloud')->willReturn([
            'conflict' => true,
            'conflict_note' => 'entity_type hors allowlist de synchronisation — enregistrement rejeté.',
        ]);

        $engine->push($node);

        $fresh = $item->fresh();

        $this->assertSame('conflict', $fresh?->status);
        $this->assertNotNull($fresh?->conflict_at, 'Un conflit doit être daté pour être diagnostiqué');
        $this->assertStringContainsString('hors allowlist', (string) $fresh?->last_error);
    }

    public function test_la_cause_persistee_est_bornee(): void
    {
        $node = $this->makeNode();

        $item = SyncQueue::query()->create([
            'edge_node_id' => $node->id,
            'entity_type' => 'attendance_logs',
            'entity_id' => 'emp-long',
            'operation' => 'create',
            'payload' => ['day' => '2026-09-04'],
            'status' => 'pending',
            'attempt_count' => 0,
        ]);

        $engine = $this->createPartialMock(SyncEngineService::class, ['applyToCloud']);
        $engine->method('applyToCloud')->willThrowException(new \RuntimeException(str_repeat('x', 5000)));

        $engine->push($node);

        // Une trace de 5 ko n'a pas à être archivée dans la file : le
        // diagnostic a besoin d'un message lisible, pas d'un dump.
        $this->assertLessThanOrEqual(1000, mb_strlen((string) $item->fresh()?->last_error));
    }
}
