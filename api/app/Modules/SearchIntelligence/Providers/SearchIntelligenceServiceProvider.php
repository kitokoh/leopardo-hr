<?php

declare(strict_types=1);

namespace App\Modules\SearchIntelligence\Providers;

use App\Modules\SearchIntelligence\Domain\Contracts\LeadMetricsProviderInterface;
use App\Modules\SearchIntelligence\Infrastructure\Services\NullLeadMetricsProvider;
use App\Modules\SearchIntelligence\Infrastructure\Services\SearchIntelOverviewService;
use Illuminate\Support\ServiceProvider;

/**
 * GSI-1 (#8512) — provider du module SearchIntelligence.
 *
 * Les routes sont chargées depuis `routes/api.php` (groupe `platform`, gate
 * `platform.permission`), pas par `loadRoutesFrom` : c'est la convention du
 * dépôt (cf. `GeoServiceProvider`, `DeliveryServiceProvider`).
 *
 * Le contrat de leads est branché sur une implémentation **fail-closed** : le
 * module n'invente aucune métrique de leads tant que le BC-11 GROWTH n'a pas
 * fourni la sienne (même philosophie que GEO-08 pour le géocodage).
 */
class SearchIntelligenceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(LeadMetricsProviderInterface::class, NullLeadMetricsProvider::class);
        $this->app->singleton(SearchIntelOverviewService::class);
    }

    public function boot(): void
    {
        // Aucun boot actif : le module expose des routes et des politiques
        // passives, sans écouteur d'événement ni commande artisan.
    }
}
