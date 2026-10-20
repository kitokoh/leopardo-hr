<?php

declare(strict_types=1);

namespace App\Modules\Vtc\Providers;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Solutions\SolutionCatalogue;
use App\Http\Middleware\Vtc\VtcRoleResolver;
use App\Modules\Vtc\Application\Listeners\NotifyVtcRideAcceptedListener;
use App\Modules\Vtc\Application\Listeners\NotifyVtcRideCancelledListener;
use App\Modules\Vtc\Application\Listeners\NotifyVtcRideCompletedListener;
use App\Modules\Vtc\Application\Listeners\NotifyVtcRideExpiredListener;
use App\Modules\Vtc\Application\Listeners\StartVtcDispatchListener;
use App\Modules\Vtc\Domain\Events\VtcRideAccepted;
use App\Modules\Vtc\Domain\Events\VtcRideCancelled;
use App\Modules\Vtc\Domain\Events\VtcRideCompleted;
use App\Modules\Vtc\Domain\Events\VtcRideExpired;
use App\Modules\Vtc\Domain\Events\VtcRideRequested;
use App\Modules\Vtc\Domain\Manifests\VtcManifest;
use App\Modules\Vtc\Infrastructure\Geo\VtcDispatchableDriver;
use App\Modules\Vtc\Infrastructure\Services\VtcDiscoverableProvider;
use App\Modules\Vtc\Infrastructure\Services\VtcProfileResolver;
use App\Shared\Contracts\Geo\GeoServiceContract;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * VTC-01 (#8357, BC-35 VTC) — provider de la verticale VTC/taxi.
 *
 * Première verticale consommatrice du core géospatial `geo` (BC-34) : le
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

        // VTC-04 (#8360) — type recherchable `vtc_driver` de la registry
        // opt-in `geo.searchables` : vue d'adaptation des chauffeurs
        // `available` (dispatch au plus proche via le core geo BC-34).
        // Enregistrement paresseux via la façade transverse — inversion de
        // dépendance, garde d'isolation #5584 (le core ne connaît pas la
        // verticale, la verticale s'enregistre).
        // #8423 (M-02) — garde d'autorisation : la recherche HTTP « plus
        // proche chauffeur » expose les POSITIONS des chauffeurs — réservée
        // aux rôles vtc admin/dispatcher (console ops). Pas de périmètre
        // « chauffeur pour lui-même » : l'endpoint retourne une LISTE de
        // chauffeurs dans un rayon, pas une fiche individuelle. La garde ne
        // s'applique qu'à la couche HTTP — le dispatch interne (worker,
        // sans utilisateur authentifié) n'est jamais bloqué.
        $this->app->resolving(GeoServiceContract::class, function (GeoServiceContract $geo): void {
            $geo->registerSearchable(
                'vtc_driver',
                VtcDispatchableDriver::class,
                static fn (Employee $employee): bool => array_intersect(
                    (new VtcRoleResolver)->rolesFor($employee),
                    ['admin', 'dispatcher'],
                ) !== [],
            );
        });
    }

    public function boot(): void
    {
        // C-1 (#8773) — expositions de la passerelle publique. Le provider et le
        // resolver s'appuient tous deux sur `VtcMarketplaceService` (opt-in
        // `vtc_marketplace_operators` + feature `vtc` + société active) : une
        // seule règle de publication, donc aucune divergence possible entre
        // « apparaît dans la recherche » et « joignable au contact ».
        //
        // Tag de conteneur (inversion de dépendance, garde d'isolation #5584) :
        // `Shared/` ne référence jamais `App\Modules\*`.
        $this->app->tag([VtcDiscoverableProvider::class], 'public.discoverable');
        $this->app->tag([VtcProfileResolver::class], 'public.profile_resolver');

        // VTC-04 (#8360) — démarrage du dispatch sur demande de course
        // (découplage par événement : la cascade d'offres vit dans la file
        // `vtc`, la création reste synchrone).
        Event::listen(VtcRideRequested::class, StartVtcDispatchListener::class);

        // #8423 (M-20) — notifications de cycle de vie : ces événements
        // étaient ORPHELINS (aucun listener). Le passager est tenu informé
        // (acceptation, expiration, clôture) et le chauffeur reçoit son
        // récapitulatif — dégradation gracieuse, jamais d'échec de course
        // pour un push indisponible.
        Event::listen(VtcRideAccepted::class, NotifyVtcRideAcceptedListener::class);
        Event::listen(VtcRideExpired::class, NotifyVtcRideExpiredListener::class);
        Event::listen(VtcRideCompleted::class, NotifyVtcRideCompletedListener::class);
        Event::listen(VtcRideCancelled::class, NotifyVtcRideCancelledListener::class);

        // VTC-06 (#8362) — `vtc:purge-positions` (rétention RGPD des
        // positions chauffeurs, planifiée daily) : les commandes du module
        // ne sont pas auto-découvertes (leçon #8004 : seul
        // app/Console/Commands l'est) — enregistrement explicite.
        if ($this->app->runningInConsole()) {
            $this->commands([
                \App\Modules\Vtc\Console\Commands\PurgeVtcDriverPositionsCommand::class,
            ]);
        }
    }
}
