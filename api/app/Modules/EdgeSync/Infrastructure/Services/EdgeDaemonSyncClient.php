<?php

declare(strict_types=1);

namespace App\Modules\EdgeSync\Infrastructure\Services;

use App\Modules\EdgeSync\Domain\Models\SyncLog;
use App\Modules\EdgeSync\Domain\Models\SyncQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Edge-side sync client.
 *
 * Runs exclusively inside the `edge:sync-daemon` command, on an Edge
 * deployment (APP_ENV=edge, local SQLite database). Unlike
 * {@see \App\Modules\EdgeSync\Application\Services\SyncEngineService}
 * (which applies queued records directly against whatever DB connection
 * Laravel is currently using — correct when it runs on Cloud in response to
 * a real Edge push, but a silent no-op if it were ever run on Edge, since
 * it would just write the local sync_queue back into the local database),
 * this client performs the real over-the-wire HTTP push/pull against the
 * Cloud API, matching what `EdgeNodeController::pushFromEdge()` /
 * `pullDelta()` actually expect to receive on the other end.
 */
class EdgeDaemonSyncClient
{
    public function __construct(
        private readonly string $cloudApiUrl,
        private readonly string $edgeNodeId,
        private readonly string $edgeToken,
        private readonly int $batchSize = 100,
        private readonly int $timeoutSeconds = 30,
    ) {
    }

    /**
     * Run one full push+pull cycle and record it as a SyncLog.
     */
    public function sync(): SyncLog
    {
        $log = SyncLog::create([
            'edge_node_id'       => $this->edgeNodeId,
            'direction'          => 'bidirectional',
            'status'             => 'running',
            'records_sent'       => 0,
            'records_received'   => 0,
            'conflicts_detected' => 0,
            'conflicts_resolved' => 0,
            'started_at'         => now(),
        ]);

        try {
            $pushResult = $this->push();
            $pullResult = $this->pull();

            $log->update([
                'status'           => 'success',
                'records_sent'     => $pushResult['sent'],
                'records_received' => $pullResult['received'],
                'summary'          => ['push' => $pushResult, 'pull' => $pullResult],
                'finished_at'      => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('[EdgeSync Daemon] Edge -> Cloud sync failed', [
                'edge_node_id' => $this->edgeNodeId,
                'error'        => $e->getMessage(),
            ]);

            $log->update([
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
                'finished_at'   => now(),
            ]);
        }

        return $log->fresh();
    }

    /**
     * Push locally-queued records to the Cloud via
     * POST /api/v1/edge-node/{nodeId}/push.
     *
     * @return array{sent:int, failed:int}
     */
    public function push(): array
    {
        $pending = SyncQueue::where('status', 'pending')
            ->orderBy('created_at')
            ->limit($this->batchSize)
            ->get();

        if ($pending->isEmpty()) {
            return ['sent' => 0, 'failed' => 0];
        }

        $records = $pending->map(static fn (SyncQueue $item) => [
            'entity_type' => $item->entity_type,
            'entity_id'   => $item->entity_id,
            'operation'   => $item->operation,
            'payload'     => $item->payload,
        ])->all();

        $response = Http::withToken($this->edgeToken)
            ->timeout($this->timeoutSeconds)
            ->post("{$this->cloudApiUrl}/api/v1/edge-node/{$this->edgeNodeId}/push", [
                'records' => $records,
            ]);

        if ($response->failed()) {
            Log::warning('[EdgeSync Daemon] Push to Cloud failed', [
                'edge_node_id' => $this->edgeNodeId,
                'status'       => $response->status(),
                'body'         => $response->body(),
            ]);

            foreach ($pending as $item) {
                $newStatus = $item->attempt_count + 1 >= 5 ? 'failed' : 'pending';
                $item->update([
                    'status'        => $newStatus,
                    'attempt_count' => $item->attempt_count + 1,
                ]);
            }

            return ['sent' => 0, 'failed' => $pending->count()];
        }

        foreach ($pending as $item) {
            $item->update(['status' => 'synced', 'synced_at' => now()]);
        }

        return ['sent' => $pending->count(), 'failed' => 0];
    }

    /**
     * Pull the Cloud's delta via GET /api/v1/edge-node/{nodeId}/pull and
     * apply it to the local Edge database.
     *
     * @return array{received:int}
     */
    public function pull(): array
    {
        $response = Http::withToken($this->edgeToken)
            ->timeout($this->timeoutSeconds)
            ->get("{$this->cloudApiUrl}/api/v1/edge-node/{$this->edgeNodeId}/pull");

        if ($response->failed()) {
            Log::warning('[EdgeSync Daemon] Pull from Cloud failed', [
                'edge_node_id' => $this->edgeNodeId,
                'status'       => $response->status(),
                'body'         => $response->body(),
            ]);

            return ['received' => 0];
        }

        $entities = $response->json('entities', []);
        $received = 0;

        foreach ($entities as $table => $rows) {
            if (! is_array($rows) || ! is_string($table)) {
                continue;
            }

            foreach ($rows as $row) {
                if (! is_array($row) || ! isset($row['id'])) {
                    continue;
                }

                // Local reference cache only (employees, departments, positions,
                // schedules, absence_types, ...) — the same read-only tables
                // CloudDeltaBuilder exposes. Cloud remains the source of truth;
                // this simply keeps the Edge's local SQLite mirror current.
                DB::table($table)->upsert($row, ['id']);
                $received++;
            }
        }

        return ['received' => $received];
    }
}
