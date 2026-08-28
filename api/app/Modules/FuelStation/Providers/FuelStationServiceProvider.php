<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * Enregistrement du module FuelStation (FUEL-005, issue #5799).
 *
 * Squelette DDD du module : les services sont résolus par l'autowiring
 * Laravel (contrats non requis à ce stade) ; les Policies sont déclarées
 * dans `AuthServiceProvider` (PA2-ARCH-008 — point unique).
 */
class FuelStationServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void {}
}
