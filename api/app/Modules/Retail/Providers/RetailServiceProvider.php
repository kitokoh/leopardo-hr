<?php

declare(strict_types=1);

namespace App\Modules\Retail\Providers;

use App\Modules\Retail\Infrastructure\Payments\ChargilyProvider;
use App\Modules\Retail\Infrastructure\Payments\MockProvider;
use App\Modules\Retail\Infrastructure\Payments\RetailPaymentProviderRegistry;
use App\Modules\Retail\Interfaces\Console\ReconcileRetailPaymentsCommand;
use Illuminate\Support\ServiceProvider;

/**
 * Provider du module Retail (BC-17 RETAIL, issue #7672).
 *
 * Socle domaine du module vendeur générique : migrations tenant
 * (retail_categories, retail_products), modèles DDD, Policies
 * (enregistrées au point unique `App\Providers\AuthServiceProvider`,
 * PA2-ARCH-008) et feature flag tenant `retail`
 * (`RetailFeatures::RETAIL`, mécanisme Core/Feature — companies.features).
 *
 * Paiement en ligne marketplace (#7812) : registre des providers de
 * paiement (`chargily|mock`, pattern PaymentGatewayRegistry RESTO-406),
 * selection par `config('retail.payments.provider')` — fallback env en
 * attendant les profils de paiement tenant BC-21 (PR #7732, non mergé) —
 * et commande de réconciliation `retail:payments:reconcile`.
 */
class RetailServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(RetailPaymentProviderRegistry::class, function (): RetailPaymentProviderRegistry {
            $registry = new RetailPaymentProviderRegistry;
            $registry->register(new ChargilyProvider);
            $registry->register(new MockProvider);

            return $registry;
        });
    }

    public function boot(): void
    {
        // Les Policies métier sont enregistrées centralement dans
        // App\Providers\AuthServiceProvider (règle PA2-ARCH-008).

        if ($this->app->runningInConsole()) {
            $this->commands([
                ReconcileRetailPaymentsCommand::class,
            ]);
        }
    }
}
