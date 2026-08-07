<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\Auth\Domain\Models\AuditLog;
use Illuminate\Console\Command;

/**
 * Purge les audit logs au-delà de la durée de rétention légale.
 *
 * Référence : docs/security/MATRICE_CONFORMITE_RGPD_LOI_18_07.md
 * (limitation de conservation — 24 mois) et docs/security/
 * POLITIQUE_RETENTION_DOCUMENTS.md (issue #1474). La matrice référençait
 * cette commande (`audit:purge --older-than=24months`) sans qu'elle existe —
 * elle est implémentée ici.
 */
class PurgeAuditLogsCommand extends Command
{
    protected $signature = 'audit:purge {--older-than=24 : Retention en mois pour les audit logs (defaut 24)}';

    protected $description = 'Supprime les audit logs plus vieux que la duree de retention configuree';

    public function handle(): int
    {
        $months = max(1, (int) $this->option('older-than'));
        $cutoff = now()->subMonths($months);

        $this->info("Purge des audit logs anterieurs a {$cutoff->toDateString()} ({$months} mois)...");

        $deleted = AuditLog::query()
            ->where('created_at', '<', $cutoff)
            ->delete();

        $this->info("Audit logs supprimes : {$deleted}.");

        return self::SUCCESS;
    }
}
