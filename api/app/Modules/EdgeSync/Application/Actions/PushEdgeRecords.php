<?php

declare(strict_types=1);

namespace App\Modules\EdgeSync\Application\Actions;

use App\Modules\EdgeSync\Domain\Models\EdgeNode;
use App\Modules\EdgeSync\Domain\Models\SyncQueue;
use App\Modules\EdgeSync\Infrastructure\Jobs\ProcessSyncQueueJob;

/**
 * Persiste les enregistrements poussés par un noeud Edge dans la file de
 * synchronisation et déclenche leur traitement asynchrone.
 */
class PushEdgeRecords
{
    /**
     * @param  array<int, array{entity_type: string, entity_id: string, operation: string, payload: array<string, mixed>}>  $records
     */
    public function execute(EdgeNode $node, array $records): int
    {
        foreach ($records as $record) {
            SyncQueue::create([
                'edge_node_id' => $node->id,
                'entity_type' => $record['entity_type'],
                'entity_id' => $record['entity_id'],
                'operation' => $record['operation'],
                'payload' => $record['payload'],
                'status' => 'pending',
                'attempt_count' => 0,
            ]);
        }

        ProcessSyncQueueJob::dispatch($node->id);

        $node->update(['last_seen_at' => now()]);

        return count($records);
    }
}
