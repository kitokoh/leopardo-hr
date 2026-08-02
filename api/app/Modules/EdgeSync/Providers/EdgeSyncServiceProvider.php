<?php

declare(strict_types=1);

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
            __DIR__ . '/../../../../config/edge.php',
            'edge'
        );
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');
        // EdgeSync migrations live in the central api/database/migrations/tenant/ directory
        // (2026_06_29_000001_create_edge_sync_tables.php) alongside all other modules.
        // The module-local database/migrations/ directory has been removed to eliminate
        // the duplicate-migration collision that caused `migrate:fresh` failures (#1394).
    }
}
