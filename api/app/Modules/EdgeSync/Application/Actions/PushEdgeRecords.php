<?php

declare(strict_types=1);

namespace App\Modules\EdgeSync\Application\Actions;

use App\Modules\EdgeSync\Domain\Models\EdgeNode;
use App\Modules\EdgeSync\Domain\Models\SyncQueue;
use App\Modules\EdgeSync\Infrastructure\Jobs\ProcessSyncQueueJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Persiste les enregistrements poussés par un noeud Edge dans la file de
 * synchronisation et déclenche leur traitement asynchrone.
 *
 * #6554 (audit fiabilité M9/M10) : l'insertion est désormais atomique —
 * transaction + clé de dédup (`dedup_key`, index unique
 * `sync_queue_dedup_unique`) — pour qu'un rejeu (retry HTTP, double push
 * concurrent) ne crée JAMAIS de doublon dans sync_queue. Le résultat est
 * renvoyé PAR enregistrement (`queued` | `duplicate`) pour que le noeud
 * Edge puisse marquer synced uniquement ce qui est réellement accepté.
 */
class PushEdgeRecords
{
    /**
     * @param  array<int, array{entity_type: string, entity_id: string, operation: string, payload: array<string, mixed>}>  $records
     *
     * @return array{queued: int, results: list<array{entity_type: string, entity_id: string, operation: string, status: string}>}
     */
    public function execute(EdgeNode $node, array $records): array
    {
        /** @var list<array{entity_type: string, entity_id: string, operation: string, status: string}> $results */
        $results = [];

        DB::transaction(function () use ($node, $records, &$results): void {
            foreach ($records as $record) {
                $dedupKey = hash('sha256', implode("\0", [
                    $node->id,
                    $record['entity_type'],
                    $record['entity_id'],
                    $record['operation'],
                    json_encode($record['payload'], JSON_THROW_ON_ERROR),
                ]));

                // insertOrIgnore : l'index unique (edge_node_id, dedup_key)
                // absorbe les rejeux concurrents — un seul enregistrement par
                // (noeud, entité, opération, payload) dans la file.
                $inserted = DB::table('sync_queue')->insertOrIgnore([
                    'id'            => (string) Str::uuid(),
                    'edge_node_id'  => $node->id,
                    'entity_type'   => $record['entity_type'],
                    'entity_id'     => $record['entity_id'],
                    'operation'     => $record['operation'],
                    'payload'       => json_encode($record['payload'], JSON_THROW_ON_ERROR),
                    'status'        => 'pending',
                    'attempt_count' => 0,
                    'dedup_key'     => $dedupKey,
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ]);

                $results[] = [
                    'entity_type' => $record['entity_type'],
                    'entity_id'   => $record['entity_id'],
                    'operation'   => $record['operation'],
                    'status'      => $inserted > 0 ? 'queued' : 'duplicate',
                ];
            }
        });

        ProcessSyncQueueJob::dispatch($node->id);

        $node->update(['last_seen_at' => now()]);

        $queued = count(array_filter($results, static fn (array $result): bool => $result['status'] === 'queued'));

        return ['queued' => $queued, 'results' => $results];
    }
}
