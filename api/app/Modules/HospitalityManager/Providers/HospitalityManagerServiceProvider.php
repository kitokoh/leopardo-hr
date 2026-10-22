<?php

declare(strict_types=1);

namespace App\Modules\HospitalityManager\Providers;

use App\Core\Solutions\SolutionCatalogue;
use App\Modules\HospitalityManager\Console\Commands\HospitalityExpirePendingReservationsCommand;
use App\Modules\HospitalityManager\Domain\Solution\HospitalityManagerManifest;
use App\Modules\HospitalityManager\Infrastructure\Services\HospitalityDiscoverableProvider;
use App\Modules\HospitalityManager\Infrastructure\Services\HospitalityProfileResolver;
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
        // SUPERAPP-P0 — la verticale hebergement expose ses etablissements
        // PUBLICS a la decouverte unifiee (`/public/discover`).
        $this->app->tag([HospitalityDiscoverableProvider::class], 'public.discoverable');
        // SUPERAPP-P1 (#8760) — resolution fail-closed du profil pour le
        // contact public (aucune duplication des gardes : meme requete).
        $this->app->tag([HospitalityProfileResolver::class], 'public.profile_resolver');

        $this->app->singleton(SolutionCatalogue::class, function (): SolutionCatalogue {
            return new SolutionCatalogue;
        });

        $this->app->resolving(SolutionCatalogue::class, function (SolutionCatalogue $catalogue): void {
            $catalogue->register('hospitality', static fn (): HospitalityManagerManifest => new HospitalityManagerManifest);
        });
    }

    public function boot(): void
    {
        // Commandes artisan du module (enregistrement explicite, pattern CRM
        // #5729 / TravelAgency) : consommée par le scheduler (routes/console.php).
        $this->commands([
            HospitalityExpirePendingReservationsCommand::class,
        ]);
    }
}
