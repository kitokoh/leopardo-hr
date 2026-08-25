<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\CompanySetting;
use App\Core\Tenant\TenantManager;
use Illuminate\Console\Command;

/**
 * Purge les audit logs au-delà de la durée de rétention légale.
 *
 * Référence : docs/security/MATRICE_CONFORMITE_RGPD_LOI_18_07.md
 * (limitation de conservation — 24 mois) et docs/security/
 * POLITIQUE_RETENTION_DOCUMENTS.md (issue #1474). La matrice référençait
 * cette commande (`audit:purge --older-than=24months`) sans qu'elle existe —
 * elle est implémentée ici.
 *
 * #5439 — journal d'audit global : la rétention devient **configurable par
 * entreprise** via la clé `CompanySetting` `audit_retention_months` (défaut
 * 36 mois si absente). La purge est exécutée tenant par tenant (pattern
 * `TenantManager::withinTenant`, même approche que
 * `biometric:purge-expired`) et chaque purge est journalisée dans
 * `audit_logs` (action `audit.purge`) pour traçabilité RGPD.
 *
 * Usage :
 *   php artisan audit:purge [--older-than=36] [--company=<uuid>] [--dry-run]
 */
class PurgeAuditLogsCommand extends Command
{
    protected $signature = 'audit:purge
        {--older-than= : Retention en mois (defaut : CompanySetting audit_retention_months, sinon 36)}
        {--company= : UUID de la societe (tenant) cible — sinon toutes les societes}
        {--dry-run : Affiche les purges prevues sans rien ecrire}';

    protected $description = 'Supprime les audit logs plus vieux que la duree de retention configuree (par entreprise)';

    public function handle(TenantManager $tenantManager): int
    {
        $monthsOption = (string) ($this->option('older-than') ?? '');
        if ($monthsOption !== '' && (! ctype_digit($monthsOption) || (int) $monthsOption < 1)) {
            $this->error(sprintf('Valeur invalide pour --older-than="%s" : entier positif attendu (ex: 36).', $monthsOption));

            return self::FAILURE;
        }

        $globalMonths = $monthsOption !== '' ? (int) $monthsOption : 36;
        $dryRun = (bool) $this->option('dry-run');
        $companyId = (string) ($this->option('company') ?? '');

        if ($companyId !== '') {
            $company = Company::query()->find($companyId);
            if ($company === null) {
                $this->error(sprintf('Societe introuvable : %s', $companyId));

                return self::FAILURE;
            }
            $companies = [$company];
        } else {
            $companies = Company::query()->orderBy('name')->get()->all();
        }

        $totalDeleted = 0;
        $totalKept = 0;

        foreach ($companies as $company) {
            [$deleted, $kept] = $tenantManager->withinTenant($company, function () use ($company, $globalMonths, $dryRun): array {
                $months = $this->retentionMonthsFor($company, $globalMonths);
                $cutoff = now()->subMonths($months);

                $query = AuditLog::query()->where('created_at', '<', $cutoff);
                $kept = (int) (clone $query)->count();
                $deleted = 0;

                if (! $dryRun) {
                    $deleted = (int) (clone $query)->delete();

                    AuditLog::record(
                        'audit',
                        'audit.purge',
                        null,
                        null,
                        [],
                        ['cutoff' => $cutoff->toISOString(), 'retention_months' => $months, 'deleted' => $deleted, 'kept' => $kept],
                    );
                } else {
                    $this->line(sprintf(
                        '  [dry-run] %s : %d entrée(s) à purger (rétention %d mois, cutoff %s)',
                        (string) $company->name,
                        $kept,
                        $months,
                        $cutoff->toDateString(),
                    ));
                }

                return [$deleted, $kept];
            });

            $totalDeleted += $deleted;
            $totalKept += $kept;

            if (! $dryRun) {
                $this->info(sprintf(
                    '%s : %d entrée(s) purgée(s), %d conservée(s) (rétention %d mois).',
                    (string) $company->name,
                    $deleted,
                    $kept,
                    $this->retentionMonthsFor($company, $globalMonths),
                ));
            }
        }

        $this->info(sprintf('Total : %d entrée(s) purgée(s), %d conservée(s) sur %d société(s).', $totalDeleted, $totalKept, count($companies)));

        return self::SUCCESS;
    }

    /**
     * Rétention de l'entreprise : clé CompanySetting `audit_retention_months`
     * (par défaut $fallback). #5439.
     */
    private function retentionMonthsFor(Company $company, int $fallback): int
    {
        $raw = CompanySetting::query()->where('key', 'audit_retention_months')->value('value');
        if ($raw === null || ! ctype_digit((string) $raw) || (int) $raw < 1) {
            return $fallback;
        }

        return (int) $raw;
    }
}
