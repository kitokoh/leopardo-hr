<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Providers;

use App\Core\Solutions\SolutionCatalogue;
use App\Modules\HealthManager\Domain\Solution\HealthManagerManifest;
use Illuminate\Support\ServiceProvider;

/**
 * Module HealthManager — enregistre le manifest de solution dans le
 * catalogue (allowlist, fail-closed) — HC-001 (#7785).
 *
 * Le catalogue n'importe jamais `App\Modules\*` (garde d'isolation #5584) :
 * l'enregistrement se fait par inversion de dépendance via `resolving()`.
 */
class HealthManagerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SolutionCatalogue::class, function (): SolutionCatalogue {
            return new SolutionCatalogue;
        });

        $this->app->resolving(SolutionCatalogue::class, function (SolutionCatalogue $catalogue): void {
            $catalogue->register('healthmanager', static fn (): HealthManagerManifest => new HealthManagerManifest);
        });
    }
}
