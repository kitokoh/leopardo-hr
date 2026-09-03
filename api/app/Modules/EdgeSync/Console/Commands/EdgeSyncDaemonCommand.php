<?php

namespace App\Modules\EdgeSync\Console\Commands;

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

    /**
     * #6559 (audit fiabilité) — arrêt gracieux : le daemon tourne souvent en
     * PID 1 dans le conteneur edge-sync ; un PID 1 IGNORE SIGTERM par défaut
     * (disposition kernel), donc `docker stop` restait bloqué jusqu'au
     * SIGKILL. Un handler pcntl + sommeil par paliers de 1 s permet de
     * terminer en < 1 s après le SIGTERM/SIGINT.
     */
    private bool $shouldStop = false;

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

        // #6559 — handler SIGTERM/SIGINT (arrêt Docker gracieux). Garde
        // function_exists : pcntl peut être absent selon le build PHP.
        if (function_exists('pcntl_signal')) {
            pcntl_async_signals(true);
            pcntl_signal(SIGTERM, function (): void {
                $this->shouldStop = true;
                $this->info('[EdgeSync Daemon] SIGTERM received — stopping after current cycle.');
            });
            pcntl_signal(SIGINT, function (): void {
                $this->shouldStop = true;
                $this->info('[EdgeSync Daemon] SIGINT received — stopping after current cycle.');
            });
        }

        // Lecteur via closure : PHPStan ne peut pas constant-fold un drapeau
        // muté uniquement par les handlers de signal (sinon « negated boolean
        // expression is always true » sur `! $this->shouldStop`).
        $stopRequested = fn (): bool => $this->shouldStop;

        do {
            try {
                $this->info('[EdgeSync Daemon] Running sync for node '.(string) $nodeId);
                $log = $client->sync();

                if ($log->status === 'success') {
                    $this->info(sprintf(
                        '[EdgeSync Daemon] Sync complete — sent:%d recv:%d',
                        $log->records_sent,
                        $log->records_received
                    ));
                } else {
                    $this->warn('[EdgeSync Daemon] Sync failed: '.((string) ($log->error_message ?? 'unknown error')));
                }
            } catch (\Throwable $e) {
                $this->error('[EdgeSync Daemon] Error: '.$e->getMessage());
            }

            if ($runOnce || $stopRequested()) {
                break;
            }

            // #6559 — sommeil par paliers d'1 s au lieu d'un sleep() unique de
            // N minutes : un SIGTERM (docker stop) est pris en compte en
            // moins d'une seconde, l'arrêt du conteneur n'est plus bloqué.
            $remainingSeconds = $interval * 60;
            $this->info("[EdgeSync Daemon] Sleeping {$interval} minutes...");
            // @phpstan-ignore-next-line
            while ($remainingSeconds > 0 && ! $stopRequested()) {
                sleep(1);
                $remainingSeconds--;
            }
            // @phpstan-ignore-next-line
        } while (! $stopRequested());
    }
}
