<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Console\Commands;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\Accounting\Domain\Models\AccountingDocumentShare;
use Illuminate\Console\Command;

/**
 * Purge des partages de documents comptables expirés (issue #5430).
 *
 * Un partage (`accounting_document_shares`) expiré n'est plus résoluble
 * (`DocumentShareService::resolve()` → null) mais la ligne n'était jamais
 * supprimée : les données RGPD inutiles s'accumulaient. Cette commande purge
 * les partages expirés au-delà d'un délai de grâce de rétention (défaut 30 j
 * après expiration), par entreprise active (isolation tenant préservée).
 */
final class PurgeExpiredSharesCommand extends Command
{
    protected $signature = 'accounting:purge-expired-shares
        {--grace-days=30 : Delai de grace en jours apres expiration avant purge}
        {--dry-run : Affiche les compteurs sans rien supprimer}';

    protected $description = 'Purge les partages de documents comptables expires (delai de grace configurable)';

    public function handle(TenantManager $tenants): int
    {
        $graceDays = max(0, (int) $this->option('grace-days'));
        $dryRun = (bool) $this->option('dry-run');
        $cutoff = now()->subDays($graceDays);
        $total = 0;

        /** @var list<Company> $companies */
        $companies = Company::query()->where('status', 'active')->orderBy('id')->get();

        foreach ($companies as $company) {
            $count = $tenants->withinTenant($company, function () use ($cutoff, $dryRun): int {
                $query = AccountingDocumentShare::query()
                    ->whereNotNull('expires_at')
                    ->where('expires_at', '<', $cutoff);

                $count = (clone $query)->count();
                if ($count > 0 && ! $dryRun) {
                    $query->delete();
                }

                return $count;
            });

            if ($count > 0) {
                $total += $count;
                $label = (string) ($company->name ?? $company->getKey());
                $this->line(sprintf(
                    '%s : %d partage(s) %s',
                    $label,
                    $count,
                    $dryRun ? 'a purger' : 'purges',
                ));
            }
        }

        $this->info(sprintf('Total : %d partage(s) %s.', $total, $dryRun ? 'a purger (dry-run)' : 'purges'));

        return self::SUCCESS;
    }
}
