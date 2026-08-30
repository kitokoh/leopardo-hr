<?php

namespace App\Console\Commands;

use App\Modules\EdgeSync\Infrastructure\Services\EdgeDaemonSyncClient;
use Illuminate\Console\Command;

/**
 * Long-running sync daemon for Edge deployments.
 * Runs inside the edge-sync Docker container (APP_ENV=edge, local SQLite).
 *
 * This daemon is the *only* Edge-side sync entry point: it reads the local
 * sync_queue and performs a real HTTP push/pull against the Cloud API via
 * {@see EdgeDaemonSyncClient}. It must never call
 * App\Modules\EdgeSync\Application\Services\SyncEngineService directly —
 * that service's applyToCloud()/CloudDeltaBuilder logic is Cloud-only and is
 * invoked exclusively by EdgeNodeController::pushFromEdge()/pullDelta() in
 * response to this daemon's HTTP calls, never by the daemon's own loop.
 */
class EdgeSyncDaemonCommand extends Command
{
    protected $signature = 'edge:sync-daemon {--once : Run a single push/pull cycle and exit instead of looping forever. Used by integration tests (issue #1296) and any one-shot/cron invocation.}';

    protected $description = 'Run the Edge sync daemon (pushes/pulls over HTTP to the Cloud API)';

    public function handle(): void
    {
        $this->info('[EdgeSync Daemon] Starting...');

        // Issue #3564 : env() lu UNIQUEMENT via config/edge.php — l'entrypoint
        // edge exécute `php artisan config:cache`, après quoi env() renvoie
        // null hors fichiers config (fallbacks `?? env(...)` morts).
        $nodeId = config('edge.node_id');
        $token = config('edge.edge_token');
        $cloudApiUrl = config('edge.cloud_api_url');
        $interval = (int) config('edge.sync_interval_minutes', 15);
        $batchSize = (int) config('edge.batch_size', 100);

        if (! $nodeId || ! $token || ! $cloudApiUrl) {
            $this->error('[EdgeSync Daemon] EDGE_NODE_ID, EDGE_TOKEN or CLOUD_API_URL not set. Exiting.');

            return;
        }

        if ((bool) config('edge.force_offline', false)) {
            $this->warn('[EdgeSync Daemon] FORCE_OFFLINE is enabled — daemon will not contact the Cloud. Exiting.');

            return;
        }

        $client = new EdgeDaemonSyncClient(
            cloudApiUrl: rtrim((string) $cloudApiUrl, '/'),
            edgeNodeId: (string) $nodeId,
            edgeToken: (string) $token,
            batchSize: $batchSize,
        );

        $runOnce = (bool) $this->option('once');

        do {
            try {
                $this->info('[EdgeSync Daemon] Running sync for node '.$nodeId);
                $log = $client->sync();

                if ($log->status === 'success') {
                    $this->info(sprintf(
                        '[EdgeSync Daemon] Sync complete — sent:%d recv:%d',
                        $log->records_sent,
                        $log->records_received
                    ));
                } else {
                    $this->warn('[EdgeSync Daemon] Sync failed: '.($log->error_message ?? 'unknown error'));
                }
            } catch (\Throwable $e) {
                $this->error('[EdgeSync Daemon] Error: '.$e->getMessage());
            }

            if ($runOnce) {
                break;
            }

            $this->info("[EdgeSync Daemon] Sleeping {$interval} minutes...");
            sleep($interval * 60);
        } while (true);
    }
}
