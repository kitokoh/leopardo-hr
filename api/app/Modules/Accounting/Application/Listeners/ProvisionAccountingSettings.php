<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Application\Listeners;

use App\Core\Tenant\TenantManager;
use App\Events\CompanyCreated;
use App\Modules\Accounting\Application\Actions\AccountingSettingsDefaults;
use App\Modules\Accounting\Domain\Models\AccountingSettings;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Issue #5232 — défauts pays appliqués à la création d'entreprise.
 *
 * À la création d'une entreprise (événement CompanyCreated, déclenché par les
 * flux de provisioning Billing : essai guidé + inscription vérifiée), une ligne
 * AccountingSettings est créée avec les défauts dérivés du registre
 * CountryDefaults (devise, langue, TVA standard, séries de numérotation).
 *
 * Additif et non bloquant : l'événement est dispatché APRÈS le reset du tenant
 * (ProvisionGuidedTrial/VerifyTrialSignup) — on re-entre dans le contexte
 * tenant via TenantManager::withinTenant(). Si l'écriture échoue (ex. schéma
 * tenant pas encore migré), on loggue et on laisse GET /accounting/settings
 * servir les défauts à la volée : le provisioning ne casse JAMAIS la création
 * de compte.
 */
class ProvisionAccountingSettings
{
    public function handle(CompanyCreated $event): void
    {
        /** @var TenantManager $tenantManager */
        $tenantManager = app(TenantManager::class);

        try {
            $tenantManager->withinTenant($event->company, function () use ($event): void {
                AccountingSettings::query()->firstOrCreate(
                    ['company_id' => $event->company->id],
                    AccountingSettingsDefaults::for($event->company->country),
                );
            });
        } catch (Throwable $exception) {
            Log::warning('accounting.settings_provision_skipped', [
                'company_id' => $event->company->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
