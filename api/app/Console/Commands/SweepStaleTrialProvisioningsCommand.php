<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Balaye les trial provisionings restés bloqués en attente du worker.
 *
 * Référence : issue #4948 — quand le worker de queue (Render,
 * `leopardo-queue-worker`) ne consomme pas la file (Redis injoignable,
 * worker down), `ProvisionDemoTenantJob` n'est jamais exécuté et le statut
 * reste `pending`/`provisioning_sandbox` indéfiniment : le prospect poll
 * `GET /trial/status` sans jamais voir `ready` ni `failed` (échec silencieux,
 * funnel d'acquisition à l'arrêt).
 *
 * Cette commande marque `failed` (fail-loud) toute demande plus vieille que
 * `--max-age-minutes` (défaut 30 min, très au-delà du backoff max du job :
 * 5 tries × [30, 60, 120, 300] ≈ 8,5 min) et logge un message d'alerte
 * visible par le monitoring. Le prospect reçoit alors un état terminal
 * (`failed`) au lieu d'une attente infinie, et l'équipe voit le problème.
 */
class SweepStaleTrialProvisioningsCommand extends Command
{
    protected $signature = 'trial-provisionings:sweep
        {--max-age-minutes=30 : Age maximal (minutes) avant de considérer un provisioning comme bloqué}
        {--dry-run : Affiche ce qui serait marqué failed sans écrire}';

    protected $description = 'Marque failed les trial provisionings bloqués (worker jamais exécuté, issue #4948)';

    public function handle(): int
    {
        $maxAge = max(5, (int) $this->option('max-age-minutes'));
        $cutoff = now()->subMinutes($maxAge);
        $dryRun = (bool) $this->option('dry-run');

        $stuck = DB::table($this->provisioningTable())
            ->whereIn('status', ['pending', 'provisioning_sandbox'])
            ->where('updated_at', '<', $cutoff)
            ->get(['id', 'email', 'provisioning_token', 'status', 'updated_at']);

        if ($stuck->isEmpty()) {
            $this->info('Aucun trial provisioning bloqué.');

            return self::SUCCESS;
        }

        $this->warn(sprintf('Trial provisionings bloqués : %d (worker de queue jamais exécuté ? #4948).', $stuck->count()));

        foreach ($stuck as $row) {
            $message = sprintf(
                'sweep: provisioning #%d (%s) bloqué en "%s" depuis %s — worker de queue non exécuté (voir #4948)',
                $row->id,
                $row->email,
                $row->status,
                (string) $row->updated_at
            );
            if ($dryRun) {
                $this->line("[dry-run] {$message}");

                continue;
            }

            DB::table($this->provisioningTable())
                ->where('id', $row->id)
                ->update([
                    'status' => 'failed',
                    'error' => 'SWEEP_TIMEOUT: worker never processed this provisioning (queue worker / Redis down ?). Issue #4948.',
                    'updated_at' => now(),
                ]);

            $this->line("[failed] {$message}");
            // Alerte visible dans les logs (monitoring Sentry/Discord).
            \Illuminate\Support\Facades\Log::warning('trial_provisionings.sweep_timeout', [
                'id' => $row->id,
                'email' => $row->email,
                'provisioning_token' => $row->provisioning_token,
                'status_before' => $row->status,
                'stuck_since' => (string) $row->updated_at,
            ]);
        }

        $this->info(sprintf('Terminé : %d provisioning(s) marqué(s) failed.', $stuck->count()));

        return self::SUCCESS;
    }

    private function provisioningTable(): string
    {
        return DB::getDriverName() === 'pgsql' ? 'public.trial_provisionings' : 'trial_provisionings';
    }
}
