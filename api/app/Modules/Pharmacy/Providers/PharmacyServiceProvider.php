<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Providers;

use App\Core\Solutions\SolutionCatalogue;
use App\Modules\Pharmacy\Domain\Solution\PharmacyManifest;
use App\Modules\Pharmacy\Infrastructure\Services\PharmacyDiscoverableProvider;
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

        // #9592 — vitrine publique Pharmacie : le module fournit son provider
        // de découverte, tagué `public.discoverable`. Même inversion de
        // dépendance que Retail/Trade/Hospitality : l'agrégateur partagé
        // (`PublicDiscoveryService`) ne référence jamais `App\Modules\*`
        // (garde d'isolation #5584).
        $this->app->tag([PharmacyDiscoverableProvider::class], 'public.discoverable');
    }
}
