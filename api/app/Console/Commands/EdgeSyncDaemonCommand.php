<?php

namespace App\Console\Commands;

use App\Modules\EdgeSync\Application\Services\SyncEngineService;
use App\Modules\EdgeSync\Domain\Models\EdgeNode;
use Illuminate\Console\Command;

/**
 * Long-running sync daemon for Edge deployments.
 * Runs inside the edge-sync Docker container.
 */
class EdgeSyncDaemonCommand extends Command
{
    protected $signature = 'edge:sync-daemon';

    protected $description = 'Run the Edge sync daemon (polls Cloud and flushes queue)';

    public function handle(SyncEngineService $syncEngine): void
    {
        $this->info('[EdgeSync Daemon] Starting...');

        $nodeId  = config('edge.node_id') ?? env('EDGE_NODE_ID');
        $interval = (int) (config('edge.sync_interval_minutes') ?? env('CLOUD_SYNC_INTERVAL_MINUTES', 15));

        if (!$nodeId) {
            $this->error('[EdgeSync Daemon] EDGE_NODE_ID not set. Exiting.');
            return;
        }

        while (true) {
            try {
                $node = EdgeNode::find($nodeId);

                if ($node && $node->isLicenseValid()) {
                    $this->info('[EdgeSync Daemon] Running sync for node ' . $nodeId);
                    $log = $syncEngine->sync($node);
                    $this->info(sprintf(
                        '[EdgeSync Daemon] Sync complete — sent:%d recv:%d conflicts:%d',
                        $log->records_sent,
                        $log->records_received,
                        $log->conflicts_detected
                    ));
                } else {
                    $this->warn('[EdgeSync Daemon] Node not found or license invalid — skipping sync.');
                }
            } catch (\Throwable $e) {
                $this->error('[EdgeSync Daemon] Error: ' . $e->getMessage());
            }

            $this->info("[EdgeSync Daemon] Sleeping {$interval} minutes...");
            sleep($interval * 60);
        }
    }
}
