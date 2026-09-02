<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Providers;

use App\Core\AI\Domain\Contracts\ModelInferencePort;
use App\Core\AI\Infrastructure\Adapters\UnavailableModelInferenceAdapter;
use App\Core\Solutions\SolutionCatalogue;
use App\Modules\FuelStation\Domain\Solution\FuelStationManifest;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

/**
 * Module FuelStation — enregistre le manifest de solution dans le
 * catalogue (allowlist). Aucune route métier avant FUEL-006.
 */
class FuelStationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SolutionCatalogue::class, function (): SolutionCatalogue {
            return new SolutionCatalogue;
        });

        $this->app->resolving(SolutionCatalogue::class, function (SolutionCatalogue $catalogue): void {
            $catalogue->register('fuel_station', static fn (): FuelStationManifest => new FuelStationManifest);
        });

        // AI-002 (#6771) : le moteur d'inférence (OCR compteurs FuelStation)
        // est remplaçable par configuration (`ai.models.inference.adapter`).
        // Défaut FAIL-CLOSED : aucun fournisseur branché → unavailable.
        // Résolution lazy (closure) pour que les tests puissent surcharger la
        // config avant la première résolution.
        $this->app->singleton(ModelInferencePort::class, function (): ModelInferencePort {
            /** @var class-string<ModelInferencePort> $adapterClass */
            $adapterClass = config('ai.models.inference.adapter') ?: UnavailableModelInferenceAdapter::class;

            $adapter = $this->app->make($adapterClass);

            if (! $adapter instanceof ModelInferencePort) {
                throw new RuntimeException(
                    "Adapter '{$adapterClass}' must implement ".ModelInferencePort::class.'.'
                );
            }

            return $adapter;
        });
    }

    public function boot(): void
    {
        // Rien à booter tant que l'API FuelStation n'existe pas (FUEL-006).
    }
}
