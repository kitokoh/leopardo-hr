<?php

declare(strict_types=1);

namespace App\Modules\Billing\Providers;

use App\Modules\Billing\Application\Contracts\TrialProvisioningReader;
use App\Modules\Billing\Infrastructure\Repositories\DatabaseTrialProvisioningReader;
use App\Modules\Billing\Infrastructure\Services\GatewaySettingsService;
use App\Modules\Billing\Infrastructure\Services\TenantPaymentProfileResolver;
use App\Shared\Contracts\Payments\PaymentGatewayConfigProviderInterface;
use App\Shared\Contracts\Payments\TenantPaymentProfileResolverInterface;
use Illuminate\Support\ServiceProvider;

class BillingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // #7726 — configuration PSP plateforme : contrat PARTAGÉ (consommé
        // aussi par Accounting, garde d'isolation des modules #5584),
        // implémentation Billing (précédence BDD → fallback env).
        $this->app->singleton(PaymentGatewayConfigProviderInterface::class, GatewaySettingsService::class);

        // #7727 — profils de paiement du tenant (clés PSP propres, IBAN,
        // mobile money) : même découpage contrat partagé / implémentation
        // Billing, consommé par le routage des encaissements d'Accounting.
        $this->app->singleton(TenantPaymentProfileResolverInterface::class, TenantPaymentProfileResolver::class);

        // #7962 — l'Action `ReportTrialProvisioningStatus` lit l'etat d'un
        // provisioning via un contrat Application/ : la facade DB reste en
        // Infrastructure (validateur de structure de modules, #6568).
        $this->app->singleton(TrialProvisioningReader::class, DatabaseTrialProvisioningReader::class);
    }

    public function boot(): void {}
}
