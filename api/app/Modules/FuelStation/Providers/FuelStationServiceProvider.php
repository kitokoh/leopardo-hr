<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Providers;

use App\Modules\FuelStation\Infrastructure\Services\FuelStationManifestService;
use Illuminate\Support\ServiceProvider;

class FuelStationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(FuelStationManifestService::class);
    }

    public function boot(): void
    {
        // Routes chargées via require dans routes/api.php
        // (routes/modules/fuelstation.php — issues #5795/#5796/#5797/#5798).
    }
}
