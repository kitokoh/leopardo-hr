<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Providers;

use App\Core\Solutions\SolutionCatalogue;
use App\Modules\FuelStation\Domain\Solution\FuelStationManifest;
use App\Modules\FuelStation\Infrastructure\Services\FuelIncidentService;
use App\Modules\FuelStation\Infrastructure\Services\FuelStockService;
use Illuminate\Support\ServiceProvider;

/**
 * Module FuelStation — enregistre le manifest de solution dans le
 * catalogue (allowlist). Aucune route métier avant FUEL-006.
 */
class FuelStationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // #5803/#5804 — services métier stock & incidents (FUEL-009/FUEL-010).
        $this->app->singleton(FuelStockService::class);
        $this->app->singleton(FuelIncidentService::class);

        $this->app->singleton(SolutionCatalogue::class, function (): SolutionCatalogue {
            return new SolutionCatalogue;
        });

        $this->app->resolving(SolutionCatalogue::class, function (SolutionCatalogue $catalogue): void {
            $catalogue->register('fuel_station', static fn (): FuelStationManifest => new FuelStationManifest);
        });
    }

    public function boot(): void
    {
        // Rien à booter tant que l'API FuelStation n'existe pas (FUEL-006).
    }
}
