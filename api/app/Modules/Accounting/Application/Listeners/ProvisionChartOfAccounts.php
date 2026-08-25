<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Application\Listeners;

use App\Core\Tenant\TenantManager;
use App\Events\CompanyCreated;
use App\Modules\Accounting\Infrastructure\Services\ChartOfAccountsService;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Issue #5422 — plan comptable provisionné à la création d'entreprise.
 *
 * Même contrat que ProvisionAccountingSettings (#5232) : additif, non
 * bloquant, re-entre dans le contexte tenant via TenantManager::withinTenant.
 * Si le schéma tenant n'est pas encore migré, on loggue et le provisioning
 * est rejoué à la première ouverture du module (ChartOfAccountsService est
 * idempotent).
 */
class ProvisionChartOfAccounts
{
    public function handle(CompanyCreated $event): void
    {
        /** @var TenantManager $tenantManager */
        $tenantManager = app(TenantManager::class);

        try {
            $tenantManager->withinTenant($event->company, function () use ($event): void {
                app(ChartOfAccountsService::class)->ensureProvisioned($event->company->id);
            });
        } catch (Throwable $exception) {
            Log::warning('accounting.chart_provision_skipped', [
                'company_id' => $event->company->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
