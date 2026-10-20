<?php

declare(strict_types=1);

namespace App\Modules\Retail\Providers;

use App\Core\Solutions\SolutionCatalogue;
use App\Events\RetailOnlineOrderDeliveryCreated;
use App\Modules\Retail\Application\Listeners\StoreRetailOrderDeliveryReference;
use App\Modules\Retail\Domain\Manifests\RetailManifest;
use App\Modules\Retail\Infrastructure\Payments\ChargilyProvider;
use App\Modules\Retail\Infrastructure\Payments\MockProvider;
use App\Modules\Retail\Infrastructure\Payments\RetailPaymentProviderRegistry;
use App\Modules\Retail\Infrastructure\Services\RetailDiscoverableProvider;
use App\Modules\Retail\Infrastructure\Services\RetailProfileResolver;
use App\Modules\Retail\Interfaces\Console\ReconcileRetailPaymentsCommand;
use Illuminate\Support\Facades\Event;
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
        if (! $this->app->bound(SolutionCatalogue::class)) {
            $this->app->singleton(SolutionCatalogue::class, static fn (): SolutionCatalogue => new SolutionCatalogue);
        }

        $this->app->resolving(SolutionCatalogue::class, function (SolutionCatalogue $catalogue): void {
            $catalogue->register(RetailManifest::CODE, static fn (): RetailManifest => new RetailManifest);
        });

        $this->app->singleton(RetailPaymentProviderRegistry::class, function (): RetailPaymentProviderRegistry {
            $registry = new RetailPaymentProviderRegistry;
            $registry->register(new ChargilyProvider);
            $registry->register(new MockProvider);

            return $registry;
        });
    }

    public function boot(): void
    {
        // C-1 (#8773) — exposion de la boutique sur la passerelle publique.
        // Source de vérité unique : `RetailMarketplaceService` (opt-in
        // `retail_online_settings.enabled` + feature `retail` + société
        // active), déjà utilisée par `GET /public/market/sellers`.
        $this->app->tag([RetailDiscoverableProvider::class], 'public.discoverable');
        $this->app->tag([RetailProfileResolver::class], 'public.profile_resolver');

        // Les Policies métier sont enregistrées centralement dans
        // App\Providers\AuthServiceProvider (règle PA2-ARCH-008).

        // Handoff BC-26 (#7811) : retour d'événement après création de la
        // livraison — Retail stocke la référence DLV-… sur SA table pour la
        // page de suivi publique (intégration par événements, registre BC).
        Event::listen(RetailOnlineOrderDeliveryCreated::class, StoreRetailOrderDeliveryReference::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                ReconcileRetailPaymentsCommand::class,
            ]);
        }
    }
}
