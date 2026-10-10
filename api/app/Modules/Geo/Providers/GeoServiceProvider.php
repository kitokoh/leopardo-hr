<?php

declare(strict_types=1);

namespace App\Modules\Geo\Providers;

use App\Modules\Geo\Console\Commands\CheckPostgisCommand;
use Illuminate\Support\ServiceProvider;

/**
 * GEO-02 (#8351, BC-33 GEO) — provider du module transverse Geo.
 *
 * Core géospatial réutilisable (distance, plus-proches, dans-un-rayon)
 * consommé par les verticales (VTC en premier, BC-34) via les contrats de
 * `App\Shared\Contracts\Geo` (GEO-03) — jamais d'import direct entre
 * verticales. Les routes du module sont chargées depuis routes/api.php
 * (groupe /v1, gate `module.geo`) — pas de loadRoutesFrom ici (pattern
 * Delivery, #6282).
 *
 * register() : bindings ports & adapters (GEO-03 calculateurs, GEO-04
 * nearest search) ajoutés au fil des issues.
 */
class GeoServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // GEO-03 (#8352) : DistanceCalculatorInterface → Postgis|Haversine.
        // GEO-04 (#8353) : NearestSearchInterface → EloquentNearestSearch.
    }

    public function boot(): void
    {
        // Les commandes du module ne sont pas auto-découvertes (leçon #8004 :
        // seul app/Console/Commands l'est) — enregistrement explicite.
        if ($this->app->runningInConsole()) {
            $this->commands([
                CheckPostgisCommand::class,
            ]);
        }
    }
}
