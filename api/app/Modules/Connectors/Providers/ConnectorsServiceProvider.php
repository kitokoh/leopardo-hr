<?php

declare(strict_types=1);

namespace App\Modules\Connectors\Providers;

use App\Modules\Connectors\Infrastructure\Services\ConnectorCatalog;
use App\Modules\Connectors\Infrastructure\Services\ConnectorConnectionTester;
use App\Modules\Connectors\Infrastructure\Services\ConnectorSchemaValidator;
use Illuminate\Support\ServiceProvider;

/**
 * #9838 (BC-14 INTEGRATION) — enregistrement du module Connecteurs.
 *
 * Les trois services sont des singletons : `ConnectorCatalog` met le catalogue
 * en cache pour la durée de la requête (un écran qui liste N connexions ne
 * fait pas N requêtes de catalogue), et les deux autres sont sans état.
 */
class ConnectorsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ConnectorCatalog::class);
        $this->app->singleton(ConnectorSchemaValidator::class);
        $this->app->singleton(ConnectorConnectionTester::class);
    }

    public function boot(): void {}
}
