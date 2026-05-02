<?php

namespace App\Providers;

use App\Contracts\FeatureDetectorInterface;
use App\Services\AnnotationReader;
use App\Services\FeatureDetector;
use App\Services\ReflectionService;
use Illuminate\Support\ServiceProvider;

/**
 * Service Provider pour les services de détection de fonctionnalités
 */
class FeatureDetectionServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Enregistrer les services de base
        $this->app->singleton(ReflectionService::class);
        $this->app->singleton(AnnotationReader::class);

        // Enregistrer le FeatureDetector
        $this->app->singleton(FeatureDetectorInterface::class, FeatureDetector::class);
        $this->app->singleton(FeatureDetector::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return [
            FeatureDetectorInterface::class,
            FeatureDetector::class,
            ReflectionService::class,
            AnnotationReader::class,
        ];
    }
}