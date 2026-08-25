<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Accounting\Infrastructure\Services\AccountingRetentionService;
use Illuminate\Console\Command;

/**
 * #5273 — Purge des documents comptables finalisés au-delà de la durée de
 * rétention légale (défaut 120 mois = 10 ans, configurable via
 * config('accounting.retention_months') ou --older-than).
 *
 * Référence : docs/security/ACCOUNTING_RETENTION.md.
 */
class AccountingPurgeExpiredCommand extends Command
{
    protected $signature = 'accounting:purge-expired
        {--older-than= : Retention en mois (defaut : config accounting.retention_months)}
        {--dry-run : Affiche les documents eligibles sans rien supprimer}';

    protected $description = 'Supprime les documents comptables finalises plus vieux que la retention legale';

    public function handle(AccountingRetentionService $retention): int
    {
        $months = $this->option('older-than') !== null
            ? max(1, (int) $this->option('older-than'))
            : (int) config('accounting.retention_months', 120);

        $dryRun = (bool) $this->option('dry-run');
        $cutoff = now()->subMonths($months)->toDateString();

        $this->info(sprintf(
            'Rétention : %d mois (documents finalisés antérieurs au %s)%s',
            $months,
            $cutoff,
            $dryRun ? ' — MODE DRY-RUN (aucune suppression)' : '',
        ));

        $documents = $retention->purge($months, $dryRun);

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
