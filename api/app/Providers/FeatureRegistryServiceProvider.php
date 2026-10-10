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
 * Service Provider pour le registre d'endpoints API (manifeste mobile)
 *
 * BOS-015 (#8202) : Enregistre ApiEndpointRegistry et conserve les alias
 * FeatureRegistryInterface / FeatureRegistry pour rétro-compatibilité 1 release.
 */
class FeatureRegistryServiceProvider extends ServiceProvider
{
    /**
     * Enregistre les services dans le conteneur IoC
     */
    public function register(): void
    {
        // Enregistrer le détecteur de fonctionnalités
        $this->app->singleton(FeatureDetectorInterface::class, function ($app) {
            return new FeatureDetector(
                $app->make('router'),
                $app->make('config')
            );
        });

        // Enregistrer l'implémentation canonique BOS-015
        $this->app->singleton(ApiEndpointRegistry::class, function ($app) {
            return new ApiEndpointRegistry(
                $app->make(FeatureDetectorInterface::class),
                $app->make('cache')
            );
        });

        $this->app->bind(ApiEndpointRegistryInterface::class, ApiEndpointRegistry::class);

        // Alias de compatibilité 1 release (BOS-015)
        $this->app->bind(FeatureRegistryInterface::class, ApiEndpointRegistry::class);
        $this->app->bind(FeatureRegistry::class, ApiEndpointRegistry::class);

        // Alias nommé
        $this->app->alias(ApiEndpointRegistryInterface::class, 'api.endpoint.registry');
        $this->app->alias(FeatureRegistryInterface::class, 'feature.registry');
    }

    /**
     * Démarre les services
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
     * Fournit les services enregistrés par ce provider
     *
     * @return array<string>
     */
    public function provides(): array
    {
        return [
            FeatureDetectorInterface::class,
            ApiEndpointRegistryInterface::class,
            ApiEndpointRegistry::class,
            FeatureRegistryInterface::class,
            FeatureRegistry::class,
            'api.endpoint.registry',
            'feature.registry',
        ];
    }
}
