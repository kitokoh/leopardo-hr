<?php

declare(strict_types=1);

namespace App\Modules\EdgeSync\Application\Actions;

use App\Modules\EdgeSync\Domain\Models\EdgeNode;
use App\Modules\EdgeSync\Domain\Models\SyncQueue;
use App\Modules\EdgeSync\Infrastructure\Jobs\ProcessSyncQueueJob;
use Illuminate\Support\Facades\DB;

/**
 * Persiste les enregistrements poussés par un noeud Edge dans la file de
 * synchronisation et déclenche leur traitement asynchrone.
 */
class PushEdgeRecords
{
    /**
     * @param  array<int, array{entity_type: string, entity_id: string, operation: string, payload: array<string, mixed>}>  $records
     * @return array{queued: int, results: array<int, array{entity_type: string, entity_id: string, operation: string, status: string}>}
     */
    public function execute(EdgeNode $node, array $records): array
    {
        $queued = 0;
        $results = [];

        // #6554 (audit fiabilité 2026-08-31) — le lot entier est inséré dans
        // UNE transaction (avant : insert ligne à ligne sans transaction → un
        // 500 en milieu de lot laissait un état partiel et la poussée suivante
        // dupliquait les enregistrements déjà insérés) et chaque enregistrement
        // est DÉDUPLIQUÉ : un doublon de poussée (ack réseau perdu, double
        // push) ne crée jamais deux lignes `pending` pour le même
        // (edge_node_id, entity_type, entity_id, operation) — le payload de la
        // ligne existante est simplement rafraîchi (dernière version gagnante).
        DB::transaction(function () use ($node, $records, &$queued, &$results): void {
            foreach ($records as $record) {
                $key = [
                    'edge_node_id' => $node->id,
                    'entity_type' => $record['entity_type'],
                    'entity_id' => $record['entity_id'],
                    'operation' => $record['operation'],
                ];

                $resultKey = [
                    'entity_type' => $record['entity_type'],
                    'entity_id' => $record['entity_id'],
                    'operation' => $record['operation'],
                ];

                /** @var SyncQueue|null $existing */
                $existing = SyncQueue::query()
                    ->where($key)
                    ->whereIn('status', ['pending', 'processing'])
                    ->first();

                if ($existing !== null) {
                    // Doublon en vol : rafraîchir la ligne existante plutôt
                    // que d'en créer une seconde (dédup #6554).
                    $existing->update([
                        'payload' => $record['payload'],
                        'attempt_count' => 0,
                    ]);
                    $queued++;
                    $results[] = $resultKey + ['status' => 'queued'];

                    continue;
                }

                SyncQueue::create($key + [
                    'payload' => $record['payload'],
                    'status' => 'pending',
                    'attempt_count' => 0,
                ]);
                $queued++;
                $results[] = $resultKey + ['status' => 'queued'];
            }
        });

        ProcessSyncQueueJob::dispatch($node->id);

        $node->update(['last_seen_at' => now()]);

        return ['queued' => $queued, 'results' => $results];
    }
}
