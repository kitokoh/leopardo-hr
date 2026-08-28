<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Providers;

use App\Console\Commands\RegisterFuelStationFeaturesCommand;
use App\Modules\FuelStation\Infrastructure\Services\FuelStationActivationService;
use App\Modules\FuelStation\Infrastructure\Services\FuelStationFeatureRegistrar;
use Illuminate\Support\ServiceProvider;

/**
 * Module FuelStation — Issue #5795 (FUEL-001).
 *
 * Enregistre les services du module (activation tenant + registre de
 * fonctionnalités). Les routes sont chargées via `api/routes/modules/fuel_station.php`.
 */
class FuelStationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(FuelStationActivationService::class);
        $this->app->singleton(FuelStationFeatureRegistrar::class);

        $this->commands([
            RegisterFuelStationFeaturesCommand::class,
        ]);
    }

    public function boot(): void
    {
        //
    }
}
