<?php

declare(strict_types=1);

namespace App\Modules\Restaurant\Providers;

use App\Core\Solutions\SolutionCatalogue;
use App\Core\Solutions\Survey\SolutionSurveyRegistry;
use App\Modules\Restaurant\Domain\Solution\RestaurantManifest;
use App\Modules\Restaurant\Domain\Survey\RestaurantSurvey;
use Illuminate\Support\ServiceProvider;

/**
 * Module Restaurant — enregistre le manifest de solution ET le questionnaire
 * de pré-qualification (allowlists serveur, fail-closed).
 *
 * Les singletons sont enregistrés avec garde `bound()` : le catalogue et le
 * registre sont partagés entre modules (FuelStation fait de même pour le
 * catalogue) — ne jamais les ré-enregistrer sans garde.
 */
class RestaurantServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if (! $this->app->bound(SolutionCatalogue::class)) {
            $this->app->singleton(SolutionCatalogue::class, static fn (): SolutionCatalogue => new SolutionCatalogue);
        }

        $this->app->resolving(SolutionCatalogue::class, function (SolutionCatalogue $catalogue): void {
            $catalogue->register('restaurant', static fn (): RestaurantManifest => new RestaurantManifest);
        });

        if (! $this->app->bound(SolutionSurveyRegistry::class)) {
            $this->app->singleton(SolutionSurveyRegistry::class, static fn (): SolutionSurveyRegistry => new SolutionSurveyRegistry);
        }

        $this->app->resolving(SolutionSurveyRegistry::class, function (SolutionSurveyRegistry $registry): void {
            $registry->register('restaurant', static fn (): RestaurantSurvey => new RestaurantSurvey);
        });
    }

    public function boot(): void
    {
        // Rien à booter : les endpoints du survey sont génériques et vivent
        // dans Core\Solutions (voir routes/modules/solutions.php).
    }
}
