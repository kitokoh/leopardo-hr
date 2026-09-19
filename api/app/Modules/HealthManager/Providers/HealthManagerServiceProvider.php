<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Providers;

use App\Core\Solutions\SolutionCatalogue;
use App\Events\SolutionActivated;
use App\Modules\HealthManager\Domain\Solution\HealthManagerManifest;
use App\Modules\HealthManager\Infrastructure\Services\HealthSpecialtySeederService;
use Illuminate\Support\Facades\Event;
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
        // HC-002 (#7786) — seed du référentiel de spécialités standards à
        // l'activation de la solution (idempotent), pattern TravelAgency
        // (SolutionActivated → seed tenant-scoped) : le core ne référence
        // jamais App\Modules\*, chaque module écoute son propre code.
        Event::listen(SolutionActivated::class, static function (SolutionActivated $event): void {
            if ($event->solution !== HealthManagerManifest::CODE) {
                return;
            }

            app(HealthSpecialtySeederService::class)->seed($event->company);
        });
    }
}
