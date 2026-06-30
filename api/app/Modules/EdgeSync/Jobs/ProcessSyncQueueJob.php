<?php

namespace App\Modules\EdgeSync\Jobs;

use App\Modules\EdgeSync\Application\Services\SyncEngineService;
use App\Modules\EdgeSync\Domain\Models\EdgeNode;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessSyncQueueJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries   = 3;
    public int $timeout = 120;
    public int $backoff = 30;

    public function __construct(public readonly string $edgeNodeId) {}

    public function handle(SyncEngineService $syncEngine): void
    {
        $node = EdgeNode::find($this->edgeNodeId);

        if (! $node) {
            Log::warning('[EdgeSync] ProcessSyncQueueJob: node not found', [
                'edge_node_id' => $this->edgeNodeId,
            ]);

            return;
        }

        if (! $node->isLicenseValid()) {
            Log::warning('[EdgeSync] ProcessSyncQueueJob: license invalid for node', [
                'edge_node_id' => $this->edgeNodeId,
            ]);

            return;
        }

        $syncEngine->push($node);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('[EdgeSync] ProcessSyncQueueJob failed permanently', [
            'edge_node_id' => $this->edgeNodeId,
            'error'        => $exception->getMessage(),
        ]);
    }
}
