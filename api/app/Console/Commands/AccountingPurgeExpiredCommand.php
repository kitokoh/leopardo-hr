<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Accounting\Infrastructure\Services\AccountingRetentionService;
use Illuminate\Console\Command;

/**
 * #5273 / #7929 — Purge des documents comptables finalisés au-delà de la
 * durée de rétention légale.
 *
 * Sans option, la rétention est résolue PAR PAYS du tenant (issue #7929 —
 * OHADA/FR/TR 120 mois, CA 72, défaut 120) ; `--older-than` force une durée
 * unique (override opérateur, comme env ACCOUNTING_RETENTION_MONTHS).
 *
 * Référence : docs/security/ACCOUNTING_RETENTION.md.
 */
class AccountingPurgeExpiredCommand extends Command
{
    protected $signature = 'accounting:purge-expired
        {--older-than= : Retention en mois (defaut : resolution par pays du tenant)}
        {--dry-run : Affiche les documents eligibles sans rien supprimer}';

    protected $description = 'Supprime les documents comptables finalises plus vieux que la retention legale';

    public function handle(AccountingRetentionService $retention): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if ($this->option('older-than') !== null) {
            $months = max(1, (int) $this->option('older-than'));

            $this->info(sprintf(
                'Rétention forcée : %d mois (documents finalisés antérieurs au %s)%s',
                $months,
                now()->subMonths($months)->toDateString(),
                $dryRun ? ' — MODE DRY-RUN (aucune suppression)' : '',
            ));

            $documents = $retention->purge($months, $dryRun);
        } else {
            $this->info(sprintf(
                'Rétention résolue par pays du tenant (OHADA/FR/TR 120 mois, CA 72, défaut 120)%s',
                $dryRun ? ' — MODE DRY-RUN (aucune suppression)' : '',
            ));

            $documents = $retention->purgeByCountry($dryRun);
        }

        $this->info(sprintf('Documents comptables %s : %d.', $dryRun ? 'éligibles' : 'purgés', count($documents)));

        foreach ($documents as $document) {
            $this->line(sprintf(
                '  - #%d %s %s (%s)',
                $document->id,
                $document->number,
                $document->status,
                $document->issue_date->toDateString(),
            ));
        }

        return self::SUCCESS;
    }
}
