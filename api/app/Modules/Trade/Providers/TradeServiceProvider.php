<?php

declare(strict_types=1);

namespace App\Modules\Trade\Providers;

use App\Core\Solutions\SolutionCatalogue;
use App\Core\Solutions\Survey\SolutionSurveyRegistry;
use App\Modules\Trade\Domain\Manifests\TradeManifest;
use App\Modules\Trade\Domain\Survey\TradeSurvey;
use App\Modules\Trade\Infrastructure\Services\TradeDiscoverableProvider;
use App\Modules\Trade\Infrastructure\Services\TradeProfileResolver;
use Illuminate\Support\ServiceProvider;

/**
 * TRADE-001 (#8476, BC-36 TRADE) — provider de la verticale International
 * Trade OS (commerce international & B2B domestique).
 *
 * register() enregistre le manifest `trade` au catalogue central des
 * solutions (pattern BOS-014, cf. VtcServiceProvider) : activation par
 * `SolutionActivator`, installation des permissions declarees (BOS-013).
 * Aucun module requis — la verticale reste activable sur un tenant frais
 * (lecon VTC-01 : dependances optionnelles degradees gracieusement).
 *
 * Le survey vitrine `trade` est enregistre ici au `SolutionSurveyRegistry`
 * (TRADE-002, #8477) : c'est lui qui porte le parcours de choix
 * International / Domestique, puis Importateur / Exportateur / les deux, ou
 * Grossiste / Vente au detail. Les endpoints du survey sont generiques et
 * vivent dans Core\Solutions — aucun endpoint specifique a creer.
 *
 * Les routes sont chargees depuis routes/api.php (groupe /v1, gate
 * `module.trade`) — jamais de loadRoutesFrom ici (convention modules).
 *
 * @see \App\Modules\Trade\Domain\Survey\TradeSurvey
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

        if (! $this->app->bound(SolutionSurveyRegistry::class)) {
            $this->app->singleton(SolutionSurveyRegistry::class, static fn (): SolutionSurveyRegistry => new SolutionSurveyRegistry);
        }

        $this->app->resolving(SolutionSurveyRegistry::class, function (SolutionSurveyRegistry $registry): void {
            $registry->register('trade', static fn (): TradeSurvey => new TradeSurvey);
        });

        // P2-01 (#8802) — Trade devient découvrable dans l'annuaire public.
        // Premier provider d'une verticale qui n'avait AUCUNE sémantique de
        // publication : opt-in explicite, fail-closed, périmètre minimal
        // (voir la migration de la colonne `is_public`).
        $this->app->singleton(TradeDiscoverableProvider::class);
        $this->app->tag(TradeDiscoverableProvider::class, 'public.discoverable');

        // Résolution slug public -> tenant propriétaire (contact BC-13). Même
        // inversion de dépendance : Shared ne connaît jamais Modules.
        $this->app->singleton(TradeProfileResolver::class);
        $this->app->tag(TradeProfileResolver::class, 'public.profile_resolver');
    }

    public function boot(): void
    {
        // Rien a booter : le survey est servi par les endpoints generiques de
        // Core\Solutions (voir routes/modules/solutions.php).
    }
}
