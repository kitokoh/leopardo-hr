<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Accounting\Domain\Models\AccountingDocumentShare;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Purge les partages de documents comptables expirés (issue #5430).
 *
 * `accounting_document_shares` (partage tokenisé, #5225) porte `expires_at`
 * (défaut 14 j) ; la résolution rejette les partages expirés mais ne les
 * supprime jamais. Cette commande supprime les lignes expirées au-delà d'un
 * délai de grâce de rétention (RGPD : données inutiles + table qui grossit).
 *
 * Table tenant (`shared_tenants`) : la commande n'a pas de contexte tenant —
 * le scope global BelongsToCompany est inactif (ni current_company ni
 * tenant_scope_required) → la purge couvre tous les tenants du schéma
 * partagé, avec compteurs par entreprise.
 *
 * Usage :
 *   php artisan accounting:purge-expired-shares                  # défaut 30 j de grâce
 *   php artisan accounting:purge-expired-shares --grace-days=7   # grâce raccourcie
 *   php artisan accounting:purge-expired-shares --dry-run        # audit sans suppression
 */
class PurgeExpiredDocumentSharesCommand extends Command
{
    protected $signature = 'accounting:purge-expired-shares
        {--grace-days=30 : Délai de grâce en jours après expiration (défaut 30)}
        {--dry-run : Affiche les compteurs sans supprimer}';

    protected $description = 'Supprime les partages de documents comptables expirés (au-delà du délai de grâce)';

    public function handle(): int
    {
        $graceDays = max(0, (int) $this->option('grace-days'));
        $dryRun = (bool) $this->option('dry-run');
        $cutoff = now()->subDays($graceDays);

        $this->info("Partages expirés avant {$cutoff->toDateTimeString()} (grâce {$graceDays} j)...");

        /** @var \Illuminate\Support\Collection<int, object{company_id: string, total: int}> $counts */
        $counts = AccountingDocumentShare::query()
            ->where('expires_at', '<', $cutoff)
            ->select('company_id', DB::raw('count(*) as total'))
            ->groupBy('company_id')
            ->orderBy('company_id')
            ->get();

        $total = (int) $counts->sum('total');

        if ($total === 0) {
            $this->info('Aucun partage expiré à purger.');

            return self::SUCCESS;
        }

        foreach ($counts as $row) {
            $this->line(sprintf('  - entreprise %s : %d partage(s)', $row->company_id, $row->total));
        }

        if ($dryRun) {
            $this->warn("[dry-run] {$total} partage(s) seraient supprimés (aucune suppression effectuée).");

            return self::SUCCESS;
        }

        $deleted = AccountingDocumentShare::query()
            ->where('expires_at', '<', $cutoff)
            ->delete();

        $this->info("Partages supprimés : {$deleted}.");

        return self::SUCCESS;
    }
}
