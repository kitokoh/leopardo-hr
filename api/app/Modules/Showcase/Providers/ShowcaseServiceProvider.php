<?php

declare(strict_types=1);

namespace App\Modules\Showcase\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * Provider du module Showcase (BC-27 SHOWCASE, issues #6865).
 *
 * Socle domaine de la vitrine publique : migration tenant `company_showcases`,
 * modele DDD, Policy (enregistree au point unique
 * `App\Providers\AuthServiceProvider`, PA2-ARCH-008) et feature flag tenant
 * `company_showcase` (`ShowcaseFeatures::COMPANY_SHOWCASE`, mecanisme
 * Core/Feature — companies.features).
 *
 * Les routes/controleurs (API privee + publique) arrivent avec V-SECTIONS-API
 * (#6866) et V-PUBLIC-API (#6867) ; le provider reste volontairement minimal
 * tant qu'il n'a pas de service a binder.
 */
class ShowcaseServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Rien a binder au socle domaine — les contrats Infrastructure
        // seront enregistres au fil des lots API (#6866, #6867).
    }

    public function boot(): void
    {
        // Les Policies metier sont enregistrees centralement dans
        // App\Providers\AuthServiceProvider (regle PA2-ARCH-008).
    }
}
