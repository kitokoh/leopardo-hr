<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * Provider du module Catalog (BC-28 CATALOG, issues #6880).
 *
 * Socle domaine du catalogue produits B2B : migrations tenant
 * (catalog_categories, catalog_products), modèles DDD, Policies
 * (enregistrées au point unique `App\Providers\AuthServiceProvider`,
 * PA2-ARCH-008) et feature flag tenant `b2b_catalog`
 * (`CatalogFeatures::B2B_CATALOG`, mécanisme Core/Feature — companies.features).
 *
 * Les routes/contrôleurs (API privée + publique) arrivent avec C-API (#6881)
 * et les issues suivantes du programme #6877→#6891 ; le provider reste
 * volontairement minimal tant qu'il n'a pas de service à binder.
 */
class CatalogServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Rien à binder au socle domaine — les contrats Infrastructure
        // seront enregistrés au fil des lots API (C-API #6881, C-PUBLIC #6882).
    }

    public function boot(): void
    {
        // Les Policies métier sont enregistrées centralement dans
        // App\Providers\AuthServiceProvider (règle PA2-ARCH-008).
    }
}
