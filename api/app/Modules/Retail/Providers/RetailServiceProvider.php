<?php

declare(strict_types=1);

namespace App\Modules\Retail\Providers;

use App\Events\RetailOnlineOrderDeliveryCreated;
use App\Modules\Retail\Application\Listeners\StoreRetailOrderDeliveryReference;
use App\Modules\Retail\Domain\Contracts\RetailPaymentProviderContract;
use App\Modules\Retail\Infrastructure\Services\LoggingRetailPaymentProviderAdapter;
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
 * Les briques POS/stocks/tickets arrivent avec les issues suivantes du
 * programme BC-17 ; le provider reste volontairement minimal tant qu'il
 * n'a pas de service à binder (pattern CatalogServiceProvider #6880).
 */
class RetailServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Port PSP marketplace (#7812, chantier BC-21) : seam journalisé tant
        // que les profils de paiement tenant (bc/bc21-paiements-encaissement)
        // ne sont pas mergés — pattern DeliveryAccountingContract
        // (DELIVERY-205). Remplacer CE binding suffit à brancher le PSP réel.
        $this->app->singleton(RetailPaymentProviderContract::class, LoggingRetailPaymentProviderAdapter::class);
    }

    public function boot(): void
    {
        // Les Policies métier sont enregistrées centralement dans
        // App\Providers\AuthServiceProvider (règle PA2-ARCH-008).

        // Handoff BC-26 (#7811) : retour d'événement après création de la
        // livraison — Retail stocke la référence DLV-… sur SA table pour la
        // page de suivi publique (intégration par événements, registre BC).
        Event::listen(RetailOnlineOrderDeliveryCreated::class, StoreRetailOrderDeliveryReference::class);
    }
}
