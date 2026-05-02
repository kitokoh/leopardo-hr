<?php

namespace App\Providers;

use App\Contracts\FeatureRegistryInterface;
use App\Services\FeatureRegistry;
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
     *
     * @return void
     */
    public function register(): void
    {
        // Enregistrer l'interface avec son implémentation
        $this->app->bind(FeatureRegistryInterface::class, FeatureRegistry::class);

        // Enregistrer comme singleton pour optimiser les performances
        $this->app->singleton(FeatureRegistry::class, function ($app) {
            return new FeatureRegistry(
                $app->make(\App\Contracts\FeatureDetectorInterface::class),
                $app->make(\Illuminate\Cache\CacheManager::class)
            );
        });

        // Alias pour faciliter l'injection
        $this->app->alias(FeatureRegistryInterface::class, 'feature.registry');
    }

    /**
     * Bootstrap des services
     *
     * @return void
     */
    public function boot(): void
    {
        // Configuration du cache avec tags si supporté
        if ($this->app->make('cache')->getStore() instanceof \Illuminate\Cache\TaggableStore) {
            // Le cache supporte les tags, on peut utiliser des tags pour une invalidation plus fine
        }
    }

    /**
     * Services fournis par ce provider
     *
     * @return array
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