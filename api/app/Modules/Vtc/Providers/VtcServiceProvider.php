<?php

declare(strict_types=1);

namespace App\Modules\Vtc\Providers;

use App\Core\Solutions\SolutionCatalogue;
use App\Modules\Vtc\Domain\Manifests\VtcManifest;
use Illuminate\Support\ServiceProvider;

/**
 * VTC-01 (#8357, BC-34 VTC) — provider de la verticale VTC/taxi.
 *
 * Première verticale consommatrice du core géospatial `geo` (BC-33) : le
 * dispatch au chauffeur disponible le plus proche et les estimations de
 * distance passent par `App\Shared\Contracts\Geo\GeoServiceContract` (GEO-03)
 * — jamais de calcul local, jamais d'import direct d'une autre verticale
 * (règle d'isolation #6844).
 *
 * register() enregistre le manifest `vtc` au catalogue central des solutions
 * (pattern BOS-014, cf. DeliveryServiceProvider) : activation par
 * `SolutionActivator` (refus fail-closed si `geo` inactif), installation des
 * permissions déclarées (BOS-013). Les routes sont chargées depuis
 * routes/api.php (groupe /v1, gate `module.vtc`).
 */
class VtcServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Le manifest (contrat Core v2) est enregistré au catalogue central
        // (clé d'allowlist `vtc`) — fin du singleton de contrat local
        // (anti-pattern #7220-bis).
        if (! $this->app->bound(SolutionCatalogue::class)) {
            $this->app->singleton(SolutionCatalogue::class, static fn (): SolutionCatalogue => new SolutionCatalogue);
        }

        $this->app->resolving(SolutionCatalogue::class, function (SolutionCatalogue $catalogue): void {
            $catalogue->register('vtc', static fn (): VtcManifest => new VtcManifest);
        });
    }

    public function boot(): void
    {
        // Les commandes du module (VTC-06 : `vtc:purge-positions`, rétention
        // RGPD des positions chauffeurs) seront enregistrées ici — leçon
        // #8004 : seul app/Console/Commands est auto-découvert.
    }
}
