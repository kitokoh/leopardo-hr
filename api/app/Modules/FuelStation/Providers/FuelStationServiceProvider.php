<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Providers;

use App\Core\Solutions\SolutionCatalogue;
use App\Modules\FuelStation\Domain\Solution\FuelStationManifest;
use App\Modules\FuelStation\Infrastructure\Consumers\FuelAccountingOutboxConsumer;
use App\Modules\FuelStation\Infrastructure\Consumers\FuelNotificationOutboxConsumer;
use App\Modules\FuelStation\Infrastructure\Services\FuelAlertService;
use App\Modules\FuelStation\Infrastructure\Services\FuelOutboxConsumerRegistry;
use Illuminate\Support\ServiceProvider;

/**
 * Module FuelStation — enregistre le manifest de solution dans le
 * catalogue (allowlist) et les consommateurs d'outbox (FUEL-015/019).
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

        $this->app->singleton(FuelOutboxConsumerRegistry::class);
        $this->app->singleton(FuelAlertService::class);
    }

    public function boot(): void
    {
        $this->app->resolving(FuelOutboxConsumerRegistry::class, function (FuelOutboxConsumerRegistry $registry): void {
            $registry->register($this->app->make(FuelAccountingOutboxConsumer::class));
            $registry->register($this->app->make(FuelNotificationOutboxConsumer::class));
        });
    }
}
