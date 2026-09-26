<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Providers;

use App\Core\Solutions\SolutionCatalogue;
use App\Modules\Pharmacy\Domain\Solution\PharmacyManifest;
use Illuminate\Support\ServiceProvider;

/**
 * Module Pharmacy (PharmaManager) — PHARMA-001 (#7798).
 *
 * Enregistre le manifest de la solution `pharmacy` dans le catalogue
 * (allowlist fail-closed, garde d'isolation #5584 : le catalogue ne
 * référence jamais `App\Modules\*` directement — c'est le module qui
 * s'enregistre). Les policies RBAC du module sont enregistrées dans
 * AuthServiceProvider (point unique d'enregistrement, PA2-ARCH-008 / #6575).
 */
class PharmacyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->resolving(SolutionCatalogue::class, function (SolutionCatalogue $catalogue): void {
            $catalogue->register('pharmacy', static fn (): PharmacyManifest => new PharmacyManifest);
        });
    }
}
