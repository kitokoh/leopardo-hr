<?php

declare(strict_types=1);

namespace App\Modules\Geo\Providers;

use App\Modules\Geo\Console\Commands\CheckPostgisCommand;
use App\Modules\Geo\Domain\Contracts\DistanceCalculatorInterface;
use App\Modules\Geo\Infrastructure\Services\GeoCapabilities;
use App\Modules\Geo\Infrastructure\Services\GeoService;
use App\Modules\Geo\Infrastructure\Services\HaversineDistanceCalculator;
use App\Modules\Geo\Infrastructure\Services\PostgisDistanceCalculator;
use App\Shared\Contracts\Geo\GeoServiceContract;
use Illuminate\Support\ServiceProvider;

/**
 * GEO-02 (#8351, BC-33 GEO) — provider du module transverse Geo.
 *
 * Core géospatial réutilisable (distance, plus-proches, dans-un-rayon)
 * consommé par les verticales (VTC en premier, BC-34) via les contrats
 * partagés `App\Shared\Contracts\Geo\*` et les VOs `App\Shared\Geo\*` —
 * jamais d'import direct entre verticales (garde #5584). Les routes du
 * module sont chargées depuis routes/api.php (groupe /v1, gate
 * `module.geo`) — pas de loadRoutesFrom ici (pattern Delivery, #6282).
 */
class GeoServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // GEO-03 (#8352) — sélection du calculateur : PostGIS si l'extension
        // est active (précision de référence), Haversine sinon (mode dégradé
        // portable — jamais de casse pour les verticales, décision D1).
        $this->app->bind(DistanceCalculatorInterface::class, function ($app): DistanceCalculatorInterface {
            /** @var GeoCapabilities $capabilities */
            $capabilities = $app->make(GeoCapabilities::class);

            if ($capabilities->postgisAvailable()) {
                return new PostgisDistanceCalculator;
            }

            return new HaversineDistanceCalculator;
        });

        // GEO-03 (#8352) — façade transverse résolue par les verticales.
        $this->app->bind(GeoServiceContract::class, GeoService::class);

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
