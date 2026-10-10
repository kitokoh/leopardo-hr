<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\ApiEndpointRegistryInterface;
use App\Contracts\FeatureDetectorInterface;
use App\Contracts\FeatureRegistryInterface;
use App\Core\Feature\Infrastructure\Services\ApiEndpointRegistry;
use App\Core\Feature\Infrastructure\Services\FeatureDetector;
use App\Core\Feature\Infrastructure\Services\FeatureRegistry;
use Illuminate\Support\ServiceProvider;

/**
 * Service Provider pour le registre des fonctionnalites (et endpoints API mobiles BOS-015 #8202)
 */
class FeatureRegistryServiceProvider extends ServiceProvider
{
    /**
     * Enregistre les services dans le conteneur IoC
     */
    public function register(): void
    {
        // Enregistrer le detecteur de fonctionnalites
        $this->app->singleton(FeatureDetectorInterface::class, function ($app) {
            return new FeatureDetector(
                $app->make('router'),
                $app->make('config')
            );
        });

        // Enregistrer le registre de fonctionnalites
        $this->app->singleton(FeatureRegistry::class, function ($app) {
            return new FeatureRegistry(
                $app->make(FeatureDetectorInterface::class),
                $app->make('cache')
            );
        });

        $this->app->bind(FeatureRegistryInterface::class, FeatureRegistry::class);

        // BOS-015 (#8202) — Enregistrement canonique ApiEndpointRegistry
        $this->app->singleton(ApiEndpointRegistry::class, function ($app) {
            return new ApiEndpointRegistry(
                $app->make(FeatureDetectorInterface::class),
                $app->make('cache')
            );
        });
        $this->app->bind(ApiEndpointRegistryInterface::class, ApiEndpointRegistry::class);

        // Alias
        $this->app->alias(FeatureRegistryInterface::class, 'feature.registry');
        $this->app->alias(ApiEndpointRegistryInterface::class, 'api.endpoint.registry');
    }

    /**
     * Demarre les services
     */
    public function boot(): void
    {
        // Enregistrer les commandes Artisan si en mode console
        if ($this->app->runningInConsole()) {
            $this->commands([
                \App\Console\Commands\FeatureRegistryCommand::class,
                \App\Console\Commands\DemoFeatureRegistryCommand::class,
            ]);
        }
    }

    /**
     * Fournit les services enregistres par ce provider
     *
     * @return array<string>
     */
    public function provides(): array
    {
        return [
            FeatureDetectorInterface::class,
            FeatureRegistryInterface::class,
            FeatureRegistry::class,
            ApiEndpointRegistryInterface::class,
            ApiEndpointRegistry::class,
            'feature.registry',
            'api.endpoint.registry',
        ];
    }
}
