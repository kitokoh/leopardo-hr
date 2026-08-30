<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Providers;

use App\Core\Solutions\SolutionCatalogue;
use App\Modules\EduManager\Domain\Solution\EduManagerManifest;
use Illuminate\Support\ServiceProvider;

/**
 * Module EduManager — enregistre le manifest de solution dans le
 * catalogue (allowlist). Aucune route métier avant EDU-006/EDU-010.
 */
class EduManagerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SolutionCatalogue::class, function (): SolutionCatalogue {
            return new SolutionCatalogue();
        });

        $this->app->resolving(SolutionCatalogue::class, function (SolutionCatalogue $catalogue): void {
            $catalogue->register('edumanager', static fn (): EduManagerManifest => new EduManagerManifest());
        });
    }

    public function boot(): void
    {
        // Rien à booter tant que l'API EduManager n'existe pas (EDU-006/EDU-010).
    }
}
