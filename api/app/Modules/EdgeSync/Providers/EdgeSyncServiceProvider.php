<?php

namespace App\Modules\EdgeSync\Providers;

use App\Modules\EdgeSync\Application\Services\CloudDeltaBuilder;
use App\Modules\EdgeSync\Application\Services\EdgeLicenseService;
use App\Modules\EdgeSync\Application\Services\SyncEngineService;
use Illuminate\Support\ServiceProvider;

class EdgeSyncServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SyncEngineService::class);
        $this->app->singleton(EdgeLicenseService::class);
        $this->app->singleton(CloudDeltaBuilder::class);

        $this->mergeConfigFrom(
            __DIR__ . '/../../../../../config/edge.php',
            'edge'
        );
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
    }
}
