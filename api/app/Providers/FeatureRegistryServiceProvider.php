<?php

namespace App\Providers;

use App\Contracts\FeatureDetectorInterface;
use App\Contracts\FeatureRegistryInterface;
use App\Services\FeatureRegistry;
use Illuminate\Cache\CacheManager;
use Illuminate\Cache\TaggableStore;
use Illuminate\Support\ServiceProvider;

/**
 * Service Provider pour le registre des fonctionnalités
 *
 * Enregistre les services liés au registre des fonctionnalités
 * dans le conteneur de services Laravel.
 */
class FeatureRegistryServiceProvider extends ServiceProvider
{
    /**
     * Enregistre les services dans le conteneur
     */
    public function register(): void
    {
        // Enregistrer l'interface avec son implémentation
        $this->app->bind(FeatureRegistryInterface::class, FeatureRegistry::class);

        // Enregistrer comme singleton pour optimiser les performances
        $this->app->singleton(FeatureRegistry::class, function ($app) {
            return new FeatureRegistry(
                $app->make(FeatureDetectorInterface::class),
                $app->make(CacheManager::class)
            );
        });

        // Alias pour faciliter l'injection
        $this->app->alias(FeatureRegistryInterface::class, 'feature.registry');
    }

    /**
     * Bootstrap des services
     */
    public function boot(): void
    {
        // Configuration du cache avec tags si supporté
        if ($this->app->make('cache')->getStore() instanceof TaggableStore) {
            // Le cache supporte les tags, on peut utiliser des tags pour une invalidation plus fine
        }
    }

    /**
     * Services fournis par ce provider
     */
    public function provides(): array
    {
        return [
            FeatureRegistryInterface::class,
            FeatureRegistry::class,
            'feature.registry',
        ];
    }
}
