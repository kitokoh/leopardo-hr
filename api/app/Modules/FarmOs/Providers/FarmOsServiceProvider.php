<?php

declare(strict_types=1);

namespace App\Modules\FarmOs\Providers;

use App\Core\Solutions\SolutionCatalogue;
use App\Modules\FarmOs\Domain\Manifests\FarmOsManifest;
use Illuminate\Support\ServiceProvider;

/**
 * Provider du module Farm OS (verticale agricole, BC-37 AGRICULTURE — spec
 * docs/specifications/SOLUTION_FARM_OS.md).
 *
 * Responsabilité : enregistrer le manifest au catalogue des solutions
 * (BOS-014/#8201 — sans cet enregistrement, `SolutionActivator` refuse toute
 * activation de la verticale au provisioning, fail-closed). Les Policies
 * métier sont enregistrées centralement dans App\Providers\AuthServiceProvider
 * (règle PA2-ARCH-008) ; le gate `module.farm_os` est aliasé dans
 * bootstrap/app.php ; le flag `farm_os` est déclaré dans ModuleRegistry
 * (source déclarative unique, BOS-011) et projeté dans
 * config/feature-flags.php.
 *
 * Points d'enregistrement de la verticale (leçon #7785 / HC-001) :
 *   1. SolutionCatalogue (ce provider) ;
 *   2. ModuleRegistry + config/feature-flags.php + Company::KNOWN_MODULES ;
 *   3. dev-hub/governance/bounded-context-registry.json (BC-37) ;
 *   4. parité docs architecture (compteur de modules) ;
 *   5. fixture CreatesMvpSchema ;
 *   6. couverture OpenAPI ;
 *   7. préfixes protégés front + clé ClientModuleKey ;
 *   8. catalogues i18n fr/en/ar/tr.
 */
class FarmOsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if (! $this->app->bound(SolutionCatalogue::class)) {
            $this->app->singleton(SolutionCatalogue::class, static fn (): SolutionCatalogue => new SolutionCatalogue);
        }

        $this->app->resolving(SolutionCatalogue::class, function (SolutionCatalogue $catalogue): void {
            $catalogue->register(FarmOsManifest::CODE, static fn (): FarmOsManifest => new FarmOsManifest);
        });
    }

    public function boot(): void
    {
        // Les Policies métier Farm OS sont enregistrées centralement dans
        // App\Providers\AuthServiceProvider (règle PA2-ARCH-008).
    }
}
