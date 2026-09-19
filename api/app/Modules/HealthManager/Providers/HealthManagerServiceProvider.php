<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Providers;

use App\Core\Solutions\SolutionCatalogue;
use App\Modules\HealthManager\Domain\Solution\HealthManagerManifest;
use Illuminate\Support\ServiceProvider;

/**
 * Module HealthManager (BC-30 HEALTH) — HC-001 (#7785).
 *
 * Enregistre le manifest de la verticale « hôpitaux & cliniques privées »
 * dans le `SolutionCatalogue` (allowlist). Les 3 points d'enregistrement
 * obligatoires d'une solution (leçons #7220/#7235) sont couverts :
 *   1. catalogue de solutions (ici, via `$this->app->resolving`) ;
 *   2. registre des feature flags (`api/config/feature-flags.php`,
 *      scope solution, défaut false, killable) ;
 *   3. `Company::KNOWN_MODULES` (reconstruction admin plateforme + /auth/me).
 */
class HealthManagerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Pattern partagé Travel/Restaurant/FuelStation : singleton avec garde
        // `bound()` + `resolving()` — le catalogue est un service partagé
        // entre modules, ne jamais le ré-écraser.
        if (! $this->app->bound(SolutionCatalogue::class)) {
            $this->app->singleton(SolutionCatalogue::class, static fn (): SolutionCatalogue => new SolutionCatalogue);
        }

        $this->app->resolving(SolutionCatalogue::class, function (SolutionCatalogue $catalogue): void {
            $catalogue->register(HealthManagerManifest::CODE, static fn (): HealthManagerManifest => new HealthManagerManifest);
        });
    }

    public function boot(): void
    {
        // Rien à booter tant que le référentiel clinique n'existe pas (HC-002).
    }
}
