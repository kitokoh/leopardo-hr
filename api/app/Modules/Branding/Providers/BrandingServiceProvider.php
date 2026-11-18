<?php

declare(strict_types=1);

namespace App\Modules\Branding\Providers;

use App\Modules\Branding\Domain\Contracts\BrandCacheInterface;
use App\Modules\Branding\Domain\PublicSurface\PublicBrandPresenter;
use App\Modules\Branding\Domain\Services\BrandResolver;
use App\Modules\Branding\Domain\Surface\SurfaceProfileProvider;
use App\Modules\Branding\Domain\Surface\SurfaceProfileRegistry;
use App\Modules\Branding\Infrastructure\Services\BrandCache;
use Illuminate\Support\ServiceProvider;

/**
 * Service provider du BC BRANDING (BC-41, #9578/#9579).
 *
 * ## Pourquoi le registre de profils est un singleton paresseux
 *
 * Construire le registre coûte peu, mais le construire à chaque injection
 * serait une faute : un provider pourrait enregistrer deux fois, et deux
 * résolutions dans la même requête pourraient voir deux catalogues différents.
 * En `singleton`, il est construit une fois par requête et figé — c'est
 * l'invariant dont dépend la résolution du profil.
 *
 * ## Le tag `branding.surface_profiles`
 *
 * C'est le point d'extension : une verticale enregistre son provider dans SON
 * propre service provider avec
 * `$this->app->tag([RestaurantSurfaceProfile::class], 'branding.surface_profiles')`,
 * sans que Branding ne connaisse sa classe. Si une verticale n'est pas
 * installée, le tag est vide et le registre ne contient que `generic` — aucune
 * `class_exists`, aucune dépendance dure.
 *
 * Le registre n'est résolu par le conteneur que lorsque quelque chose en a
 * besoin : une requête qui ne touche pas aux Brands ne paie pas la
 * construction du catalogue.
 */
final class BrandingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SurfaceProfileRegistry::class, function ($app): SurfaceProfileRegistry {
            /** @var iterable<SurfaceProfileProvider> $tagged */
            $tagged = $app->tagged('branding.surface_profiles');

            /** @var list<SurfaceProfileProvider> $providers */
            $providers = [];
            foreach ($tagged as $provider) {
                $providers[] = $provider;
            }

            return SurfaceProfileRegistry::build($providers);
        });

        $this->app->singleton(PublicBrandPresenter::class, function ($app): PublicBrandPresenter {
            /** @var SurfaceProfileRegistry $registry */
            $registry = $app->make(SurfaceProfileRegistry::class);

            return new PublicBrandPresenter($registry);
        });

        $this->app->singleton(BrandCacheInterface::class, BrandCache::class);

        $this->app->singleton(BrandResolver::class, function ($app): BrandResolver {
            return new BrandResolver(
                $app->make(\App\Shared\Services\PublicCommerce\PublicTenantResolver::class),
                $app->make(BrandCacheInterface::class),
            );
        });
    }

    public function boot(): void
    {
        // Les profils de surface sont fournis par les verticales. Ce module
        // n'en enregistre aucun lui-même : il possède le contenant, pas le
        // contenu. C'est ce qui garantit qu'ajouter une verticale ne demande
        // aucune modification ici.

        // Laravel ne découvre que `app/Console/Commands` : une commande de
        // module doit être enregistrée explicitement (convention du dépôt,
        // cf. AccountingServiceProvider / CrmServiceProvider).
        if ($this->app->runningInConsole()) {
            $this->commands([
                \App\Modules\Branding\Console\Commands\BrandingBackfillCommand::class,
            ]);
        }
    }
}
