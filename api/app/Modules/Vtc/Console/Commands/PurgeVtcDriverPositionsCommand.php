<?php

declare(strict_types=1);

namespace App\Modules\Vtc\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Purge RGPD des positions chauffeurs (BC-34 VTC, VTC-06/#8362).
 *
 * Les positions sont des DONNÉES PERSONNELLES : rétention bornée par
 * `vtc.positions_retention_days` (défaut 30 j, spec §7) puis suppression —
 * planifiée daily (routes/console.php).
 *
 *   - IDEMPOTENTE : une seconde exécution ne supprime rien de plus (le
 *     cutoff est le seul critère) ;
 *   - AUDITÉE : chaque exécution trace cutoff, rétention et volume purgé
 *     dans le canal de logs (preuve de conformité exploitable) ;
 *   - trans-tenant par nature (maintenance plateforme du schéma partagé) :
 *     requête query builder qualifiée — jamais le scope Eloquent tenant,
 *     jamais de données lues (DELETE seul, aucune exposition).
 */
final class PurgeVtcDriverPositionsCommand extends Command
{
    protected $signature = 'vtc:purge-positions
        {--days= : Rétention en jours (défaut : vtc.positions_retention_days)}
        {--chunk=10000 : Taille des lots de suppression (protection du journal)}';

    public function __construct()
    {
        parent::__construct();

        // PA2-I18N-007 : description via le catalogue (jamais d'accentué en dur).
        $this->description = (string) __('vtc.purge_positions_description');
    }

    public function handle(): int
    {
        $days = $this->retentionDays();
        $chunk = max(100, (int) $this->option('chunk'));
        $cutoff = now()->subDays($days);

        $deleted = 0;

        // Lots bornés : une purge massive ne tient pas en une seule
        // transaction (protection WAL/verrous sur Neon). DELETE ... LIMIT
        // n'existe pas en PostgreSQL → sous-requête d'ids.
        do {
            $batch = DB::table($this->positionsTable())
                ->whereIn('id', function ($query) use ($cutoff, $chunk): void {
                    $query->select('id')
                        ->from($this->positionsTable())
                        ->where('recorded_at', '<', $cutoff)
                        ->limit($chunk);
                })
                ->delete();

            $deleted += $batch;
        } while ($batch === $chunk);

        Log::info((string) __('vtc.purge_positions_done_log'), [
            'retention_days' => $days,
            'cutoff' => $cutoff->toIso8601String(),
            'deleted_rows' => $deleted,
        ]);

        $this->info((string) __('vtc.purge_positions_done_cli', ['deleted' => $deleted, 'days' => $days, 'cutoff' => $cutoff->toDateTimeString()]));

        return self::SUCCESS;
    }

    private function retentionDays(): int
    {
        $override = $this->option('days');

        if (is_numeric($override) && (int) $override > 0) {
            return (int) $override;
        }

        $configured = config('vtc.positions_retention_days', 30);

        return is_int($configured) && $configured > 0 ? $configured : 30;
    }

    /**
     * Table tenant qualifiée : les positions vivent dans le schéma partagé
     * `shared_tenants` (tenancy « schema » verrouillée) — miroir des autres
     * maintenances trans-tenant (pattern tenantTable).
     */
    private function positionsTable(): string
    {
        return DB::getDriverName() === 'pgsql'
            ? 'shared_tenants.vtc_driver_positions'
            : 'vtc_driver_positions';
    }
}
