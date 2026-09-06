<?php

declare(strict_types=1);

namespace App\Modules\Showcase\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * Provider du module Showcase (BC-27 SHOWCASE, issues #6865).
 *
 * Socle domaine de la vitrine entreprise 1-clic : migration tenant
 * (company_showcases), modèle DDD, Policy (enregistrée au point unique
 * `App\Providers\AuthServiceProvider`, PA2-ARCH-008) et feature flag tenant
 * `company_showcase` (`ShowcaseFeatures::COMPANY_SHOWCASE`, mécanisme
 * Core/Feature — companies.features).
 *
 * Les routes/contrôleurs (API privée sections + API publique isolée)
 * arrivent avec V-SECTIONS-API (#6866) et V-PUBLIC-API (#6867) ; le provider
 * reste volontairement minimal tant qu'il n'a pas de service à binder.
 */
class ShowcaseServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Rien à binder au socle domaine — les contrats Infrastructure
        // seront enregistrés au fil des lots API (V-SECTIONS-API #6866,
        // V-PUBLIC-API #6867).
    }

    public function boot(): void
    {
        // Les Policies métier sont enregistrées centralement dans
        // App\Providers\AuthServiceProvider (règle PA2-ARCH-008).
    }
}
