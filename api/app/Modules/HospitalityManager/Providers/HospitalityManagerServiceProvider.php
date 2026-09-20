<?php

declare(strict_types=1);

namespace App\Modules\HospitalityManager\Providers;

use App\Core\Solutions\SolutionCatalogue;
use App\Modules\HospitalityManager\Domain\Solution\HospitalityManagerManifest;
use Illuminate\Support\ServiceProvider;

/**
 * Module HospitalityManager — enregistre le manifest de solution dans le
 * catalogue (allowlist, fail-closed) — HOSP-001 (#7943).
 *
 * Le catalogue n'importe jamais `App\Modules\*` (garde d'isolation #5584) :
 * l'enregistrement se fait par inversion de dépendance via `resolving()`.
 */
class HospitalityManagerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SolutionCatalogue::class, function (): SolutionCatalogue {
            return new SolutionCatalogue;
        });

        $this->app->resolving(SolutionCatalogue::class, function (SolutionCatalogue $catalogue): void {
            $catalogue->register('hospitality', static fn (): HospitalityManagerManifest => new HospitalityManagerManifest);
        });
    }
}
