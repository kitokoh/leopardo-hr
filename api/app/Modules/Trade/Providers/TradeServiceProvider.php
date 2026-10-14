<?php

declare(strict_types=1);

namespace App\Modules\Trade\Providers;

use App\Core\Solutions\SolutionCatalogue;
use App\Modules\Trade\Domain\Manifests\TradeManifest;
use Illuminate\Support\ServiceProvider;

/**
 * TRADE-001 (#8476, BC-36 TRADE) — provider de la verticale International
 * Trade OS (commerce international & B2B domestique).
 *
 * register() enregistre le manifest `trade` au catalogue central des
 * solutions (pattern BOS-014, cf. VtcServiceProvider) : activation par
 * `SolutionActivator`, installation des permissions déclarées (BOS-013).
 * Aucun module requis — la verticale reste activable sur un tenant frais
 * (leçon VTC-01 : dépendances optionnelles dégradées gracieusement).
 *
 * Le survey vitrine `trade` est ajouté en TRADE-002 (#8477) au
 * `SolutionSurveyRegistry` — point d'extension prévu du Core.
 *
 * Les routes sont chargées depuis routes/api.php (groupe /v1, gate
 * `module.trade`) — jamais de loadRoutesFrom ici (convention modules).
 */
class TradeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if (! $this->app->bound(SolutionCatalogue::class)) {
            $this->app->singleton(SolutionCatalogue::class, static fn (): SolutionCatalogue => new SolutionCatalogue);
        }

        $this->app->resolving(SolutionCatalogue::class, function (SolutionCatalogue $catalogue): void {
            $catalogue->register('trade', static fn (): TradeManifest => new TradeManifest);
        });
    }

    public function boot(): void
    {
        //
    }
}
