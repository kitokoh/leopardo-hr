<?php

declare(strict_types=1);

namespace App\Modules\Fundraising\Providers;

use App\Core\Solutions\SolutionCatalogue;
use App\Modules\Fundraising\Domain\Manifests\FundraisingManifest;
use App\Modules\Fundraising\Infrastructure\Services\FundraisingGatewayFactory;
use Illuminate\Support\ServiceProvider;

/**
 * Provider du module Fundraising (verticale cagnottes solidaires — spec
 * docs/specifications/SOLUTION_FUNDRAISING.md).
 *
 * Bindings : factory des passerelles de paiement (config/fundraising.php).
 * Les Policies métier sont enregistrées centralement dans
 * App\Providers\AuthServiceProvider (règle PA2-ARCH-008) ; le gate
 * `module.fundraising` est aliasé dans bootstrap/app.php ; le feature flag
 * `fundraising` est déclaré dans config/feature-flags.php +
 * Company::KNOWN_MODULES (3 points d'enregistrement obligatoires).
 */
class FundraisingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(FundraisingGatewayFactory::class, fn (): FundraisingGatewayFactory => new FundraisingGatewayFactory(
            is_array(config('fundraising')) ? config('fundraising') : []
        ));

        // FUND-080 : manifest au catalogue des solutions — sans lui,
        // `SolutionActivator` (fail-closed) refusait toute activation de la
        // verticale au provisioning (onboarding) ; seul l'admin plateforme
        // pouvait poser le flag. Pattern identique aux autres verticales
        // (Restaurant, Retail, …).
        if (! $this->app->bound(SolutionCatalogue::class)) {
            $this->app->singleton(SolutionCatalogue::class, static fn (): SolutionCatalogue => new SolutionCatalogue);
        }

        $this->app->resolving(SolutionCatalogue::class, function (SolutionCatalogue $catalogue): void {
            $catalogue->register(FundraisingManifest::CODE, static fn (): FundraisingManifest => new FundraisingManifest);
        });
    }

    public function boot(): void
    {
        // Les Policies métier sont enregistrées centralement dans
        // App\Providers\AuthServiceProvider (règle PA2-ARCH-008).
    }
}
