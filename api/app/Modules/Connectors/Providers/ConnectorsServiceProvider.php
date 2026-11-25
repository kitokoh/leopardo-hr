<?php

declare(strict_types=1);

namespace App\Modules\Connectors\Providers;

use App\Modules\Connectors\Infrastructure\Services\ConnectorCatalog;
use App\Modules\Connectors\Infrastructure\Services\ConnectorConnectionTester;
use App\Modules\Connectors\Infrastructure\Services\ConnectorIntegrationIndex;
use App\Modules\Connectors\Infrastructure\Services\ConnectorSchemaValidator;
use App\Modules\Connectors\Infrastructure\Services\ConnectorAiUsageTracker;
use App\Shared\Contracts\Connectors\ConnectorIntegrationRegistry;
use Illuminate\Support\ServiceProvider;

/**
 * #9846 (BC-14 INTEGRATION) — enregistrement du module Connecteurs.
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
        $this->app->singleton(ConnectorAiUsageTracker::class);

        // #9846 — registre des intégrations extérieures déjà existantes.
        // Le registre vit dans `Shared\Contracts` : Connecteurs ne connaît
        // aucun module métier, ce sont les modules propriétaires qui s'y
        // déclarent depuis leur propre provider (garde d'isolation #5584).
        $this->app->singleton(ConnectorIntegrationRegistry::class);
        $this->app->singleton(ConnectorIntegrationIndex::class);
    }

    public function boot(): void {}
}
