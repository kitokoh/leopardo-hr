<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Carbon;
use App\Notifications\EdgeNodeSilentAlert;

/**
 * Détecte les nœuds Edge qui n'ont pas envoyé de heartbeat depuis plus
 * de EDGE_SILENCE_THRESHOLD_MINUTES minutes et notifie les managers concernés.
 *
 * Planification recommandée : toutes les 5 minutes.
 *   Schedule::command('edge:detect-silent-nodes')->everyFiveMinutes()->withoutOverlapping();
 */
class DetectSilentEdgeNodes extends Command
{
    protected $signature = 'edge:detect-silent-nodes
                            {--threshold= : Seuil de silence en minutes (défaut : env EDGE_SILENCE_THRESHOLD_MINUTES ou 30)}
                            {--dry-run    : Détecte mais n\'envoie pas de notifications}';

    protected $description = 'Détecte les nœuds Edge silencieux depuis trop longtemps et alerte les managers';

    public function handle(): int
    {
        $thresholdMinutes = (int) (
            $this->option('threshold')
            ?? config('edge.silence_threshold_minutes', 30)
        );

        $dryRun   = (bool) $this->option('dry-run');
        $cutoffAt = Carbon::now()->subMinutes($thresholdMinutes);

        $this->info(sprintf(
            '[EdgeMonitor] Recherche des nœuds silencieux depuis > %d min (cutoff: %s)%s',
            $thresholdMinutes,
            $cutoffAt->toDateTimeString(),
            $dryRun ? ' [DRY-RUN]' : ''
        ));

        // ── Requête des nœuds silencieux ─────────────────────────────────
        $silentNodes = DB::table('edge_nodes as n')
            ->join('companies as c', 'c.id', '=', 'n.company_id')
            ->leftJoin('users as u', function ($join) {
                $join->on('u.company_id', '=', 'n.company_id')
                     ->where('u.role', '=', 'manager')
                     ->whereNull('u.deleted_at');
            })
            ->select([
                'n.id',
                'n.node_id',
                'n.name',
                'n.last_seen_at',
                'n.company_id',
                'n.status',
                'c.name as company_name',
                DB::raw("string_agg(u.email, ',') as manager_emails"),
            ])
            ->where('n.status', '!=', 'revoked')
            ->where(function ($q) use ($cutoffAt) {
                $q->where('n.last_seen_at', '<', $cutoffAt)
                  ->orWhereNull('n.last_seen_at');
            })
            ->where('n.alert_muted', false)
            ->groupBy('n.id', 'n.node_id', 'n.name', 'n.last_seen_at', 'n.company_id', 'n.status', 'c.name')
            ->get();

        if ($silentNodes->isEmpty()) {
            $this->info('[EdgeMonitor] Aucun nœud silencieux détecté. ✅');
            return Command::SUCCESS;
        }

        $this->warn(sprintf('[EdgeMonitor] %d nœud(s) silencieux détecté(s) :', $silentNodes->count()));

        $alertsSent = 0;

        foreach ($silentNodes as $node) {
            $silenceSince = $node->last_seen_at
                ? Carbon::parse($node->last_seen_at)->diffForHumans()
                : 'jamais vu';

            $this->line(sprintf(
                '  → [%s] %s (%s) — dernier ping : %s',
                $node->company_name,
                $node->name,
                $node->node_id,
                $silenceSince
            ));

            if ($dryRun) {
                continue;
            }

            // Mise à jour du statut en BDD
            DB::table('edge_nodes')
                ->where('id', $node->id)
                ->update([
                    'status'           => 'offline',
                    'last_alert_sent_at' => Carbon::now(),
                ]);

            // Envoi de la notification aux managers de la société
            $emails = array_filter(explode(',', $node->manager_emails ?? ''));

            if (empty($emails)) {
                Log::warning('[EdgeMonitor] Aucun manager trouvé pour le tenant', [
                    'company_id' => $node->company_id,
                    'node_id'    => $node->node_id,
                ]);
                continue;
            }

            try {
                Notification::route('mail', $emails)
                    ->notify(new EdgeNodeSilentAlert(
                        nodeName:      $node->name,
                        nodeId:        $node->node_id,
                        companyName:   $node->company_name,
                        lastSeenAt:    $node->last_seen_at
                            ? Carbon::parse($node->last_seen_at)
                            : null,
                        thresholdMins: $thresholdMinutes,
                    ));

                $alertsSent++;

                Log::info('[EdgeMonitor] Alerte envoyée', [
                    'node_id'  => $node->node_id,
                    'to'       => $emails,
                ]);
            } catch (\Throwable $e) {
                Log::error('[EdgeMonitor] Échec envoi alerte', [
                    'node_id' => $node->node_id,
                    'error'   => $e->getMessage(),
                ]);
            }
        }

        if (! $dryRun) {
            $this->info(sprintf('[EdgeMonitor] %d alerte(s) envoyée(s).', $alertsSent));
        }

        return Command::SUCCESS;
    }
}
