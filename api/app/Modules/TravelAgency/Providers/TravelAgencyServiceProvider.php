<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Providers;

use App\Modules\TravelAgency\Domain\Contracts\SolutionManifest;
use App\Modules\TravelAgency\Domain\Manifests\TravelAgencyManifest;
use Illuminate\Support\ServiceProvider;

/**
 * Provider du module TravelAgency (BC-24 TRAVEL).
 *
 * Fondations de la verticale « Agence de Voyage » (TRAVEL-101, issue #5977) :
 * portage de l'ancien projet gv-back (vente de billets en ligne) dans
 * l'architecture DDD multi-tenant Leopardo HR.
 *
 * `register()` enregistre les ports & adapters du module (contrats →
 * implémentations) ; les Policies métier seront enregistrées dans `boot()`
 * au fil des lots API (épic 3xx).
 *
 * L'activation par tenant passe par le feature flag `travelagency`
 * (companies.features) — voir EnsureTravelAgencyModuleMiddleware (TRAVEL-102)
 * et ActivateTravelAgencyAction (TRAVEL-105).
 */
class TravelAgencyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SolutionManifest::class, TravelAgencyManifest::class);
    }

    public function boot(): void
    {
        // Policies enregistrées ici dès que les modèles de l'épic 3xx existent
        // (Gate::policy(TravelX::class, TravelXPolicy::class)).
    }
}
