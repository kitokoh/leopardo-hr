<?php

declare(strict_types=1);

namespace App\Modules\Retail\Providers;

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
        // Aucun binding pour l'instant (socle fondations #7672).
    }

    public function boot(): void
    {
        // Les Policies métier sont enregistrées centralement dans
        // App\Providers\AuthServiceProvider (règle PA2-ARCH-008).
    }
}
