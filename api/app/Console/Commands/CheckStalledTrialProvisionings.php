<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\ProvisionDemoTenantJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Issue #4948 — self-healing des provisionings de démo jamais partis.
 *
 * Constat prod : avec le worker de queue down (épic #3765/#3766), une ligne
 * `trial_provisionings` reste `pending` indéfiniment — GET /trial/status
 * répond `pending` pendant des heures, aucun magic link n'est envoyé, et le
 * funnel d'acquisition est à l'arrêt (promesse « sandbox < 30 s » non tenue).
 *
 * Ce sweeper (toutes les 15 min, voir bootstrap/app.php) :
 *  1. repère les lignes `pending` plus vieilles que STALLED_AFTER_MINUTES
 *     (le job normal passe à ready/failed en < 10 min, retries comprises —
 *     une ligne pendante au-delà est définitivement orpheline) ;
 *  2. tente jusqu'à MAX_ATTEMPTS re-dispatches du job avec les arguments
 *     d'origine (company_name/country stockés depuis la migration
 *     2026_08_18_000001 — les lignes antérieures non reconstructibles sont
 *     simplement passées en failed) ;
 *  3. au-delà, passe la ligne en `failed` avec un message explicite et lève
 *     une alerte Log::error (monitoring) — le statut ne ment plus, et le
 *     prochain signup (l'anti-doublon #3951 ne réutilise que les lignes
 *     `pending`) peut repartir proprement.
 */
class CheckStalledTrialProvisionings extends Command
{
    protected $signature = 'trial:provisioning-sweep';

    protected $description = 'Re-dispatch or fail trial provisionings stuck in pending (worker stalled)';

    private const STALLED_AFTER_MINUTES = 30;

    private const MAX_ATTEMPTS = 3;

    public function handle(): int
    {
        $stalled = DB::table('trial_provisionings')
            ->where('status', 'pending')
            ->where('created_at', '<', now()->subMinutes(self::STALLED_AFTER_MINUTES))
            ->orderBy('created_at')
            ->get();

        foreach ($stalled as $row) {
            /** @var object{company_name: mixed, country: mixed, attempts: mixed, provisioning_token: mixed, email: mixed} $row */
            $this->recover($row);
        }

        // Issue #6547 (audit) : une company_request claimée en `processing`
        // par VerifyTrialSignup et jamais terminée (crash worker, exception
        // non couverte) reste bloquée `processing` pour toujours → 409 à vie
        // sur /trial/verify. Self-healing : reset en `pending` après
        // STALLED_AFTER_MINUTES — le parcours peut repartir proprement.
        $stalledRequests = DB::table('company_requests')
            ->where('status', 'processing')
            ->where('updated_at', '<', now()->subMinutes(self::STALLED_AFTER_MINUTES))
            ->orderBy('updated_at')
            ->get();

        foreach ($stalledRequests as $row) {
            DB::table('company_requests')
                ->where('id', $row->id)
                ->update(['status' => 'pending', 'updated_at' => now()]);
            Log::warning('trial.verify_stalled_claim_reset', [
                'company_request_id' => $row->id,
                'email' => $row->email ?? null,
            ]);
            $this->warn('Stalled company_request #'.(string) $row->id.' reset to pending (issue #6547).');
        }

        $this->info('Processed '.$stalled->count().' stalled trial provisioning(s), '.$stalledRequests->count().' stalled company_request(s).');

        return self::SUCCESS;
    }

    /** @param object{company_name: mixed, country: mixed, attempts: mixed, provisioning_token: mixed, email: mixed} $row */
    private function recover(object $row): void
    {
        // Ligne non reconstructible (créée avant la migration 000001) : pas
        // d'arguments pour re-dispatcher — fail immédiat, l'alerte est loggée.
        if (! is_string($row->company_name) || $row->company_name === '' || ! is_string($row->country)) {
            $this->failPermanently($row, 'sweeper:company_context_missing');

            return;
        }

        if ((int) $row->attempts >= self::MAX_ATTEMPTS) {
            $this->failPermanently($row, 'sweeper:worker_stalled_after_'.self::MAX_ATTEMPTS.'_attempts');

            return;
        }

        DB::table('trial_provisionings')
            ->where('provisioning_token', $row->provisioning_token)
            ->increment('attempts');

        ProvisionDemoTenantJob::dispatch($row->email, $row->company_name, $row->country, $row->provisioning_token);

        Log::warning('trial.provisioning_redispatch', [
            'email' => $row->email,
            'token' => $row->provisioning_token,
            'attempts' => (int) $row->attempts + 1,
            'reason' => 'stalled_pending_'.self::STALLED_AFTER_MINUTES.'_min',
        ]);

        $this->warn("Re-dispatched {$row->email} (attempt ".(int) $row->attempts + 1 .').');
    }

    /** @param object{company_name: mixed, country: mixed, attempts: mixed, provisioning_token: mixed, email: mixed} $row */
    private function failPermanently(object $row, string $reason): void
    {
        DB::table('trial_provisionings')
            ->where('provisioning_token', $row->provisioning_token)
            ->update([
                'status' => 'failed',
                'error' => $reason,
                'updated_at' => now(),
            ]);

        Log::error('trial.provisioning_failed_sweeper', [
            'email' => $row->email,
            'token' => $row->provisioning_token,
            'attempts' => (int) $row->attempts,
            'reason' => $reason,
        ]);

        $this->error("Failed {$row->email}: {$reason}");
    }
}
