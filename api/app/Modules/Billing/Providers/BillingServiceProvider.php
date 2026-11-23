<?php

declare(strict_types=1);

namespace App\Modules\Billing\Providers;

use App\Modules\Billing\Infrastructure\Connectors\PaymentGatewayIntegrationSource;
use App\Modules\Billing\Infrastructure\Connectors\TenantPaymentProfileIntegrationSource;
use App\Modules\Billing\Infrastructure\Connectors\WebhookEndpointIntegrationSource;
use App\Modules\Billing\Infrastructure\Services\GatewaySettingsService;
use App\Modules\Billing\Infrastructure\Services\TenantPaymentProfileResolver;
use App\Shared\Contracts\Connectors\ConnectorIntegrationRegistry;
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
    }

    public function boot(): void
    {
        // #9846 — Billing déclare ses intégrations extérieures auprès du
        // registre partagé de l'onglet Connecteurs. C'est le module
        // PROPRIÉTAIRE qui s'enregistre : Connecteurs n'importe pas Billing
        // (garde d'isolation #5584).
        $registry = $this->app->make(ConnectorIntegrationRegistry::class);
        $registry->register(new PaymentGatewayIntegrationSource());
        $registry->register(new TenantPaymentProfileIntegrationSource());
        $registry->register(new WebhookEndpointIntegrationSource());
    }
}
