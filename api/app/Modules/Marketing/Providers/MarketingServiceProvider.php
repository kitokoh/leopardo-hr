<?php

declare(strict_types=1);

namespace App\Modules\Marketing\Providers;

use App\Modules\Marketing\Domain\Contracts\MarketingLeadRepositoryInterface;
use App\Modules\Marketing\Domain\Contracts\SocialAccountRepositoryInterface;
use App\Modules\Marketing\Domain\Contracts\SocialPostRepositoryInterface;
use App\Modules\Marketing\Infrastructure\Repositories\MarketingLeadRepository;
use App\Modules\Marketing\Infrastructure\Repositories\SocialAccountRepository;
use App\Modules\Marketing\Infrastructure\Repositories\SocialPostRepository;
use App\Modules\Marketing\Infrastructure\Services\MarketingAssetStorage;
use App\Modules\Marketing\Infrastructure\Services\MarketingOsDashboardService;
use App\Modules\Marketing\Infrastructure\Services\MarketingPublicationModeResolver;
use App\Modules\Marketing\Infrastructure\Services\MarketingPublicationStatsService;
use App\Modules\Marketing\Infrastructure\Services\PlatformPublishingCapability;
use App\Modules\Marketing\Infrastructure\Services\Publishers\LinkedInPublisher;
use App\Modules\Marketing\Infrastructure\Services\Publishers\MetaPublisher;
use App\Modules\Marketing\Infrastructure\Services\Publishers\SocialPublisherResolver;
use App\Modules\Marketing\Infrastructure\Services\Publishers\TwitterPublisher;
use App\Modules\Marketing\Infrastructure\Services\Stats\AyrshareAnalyticsProvider;
use App\Modules\Marketing\Infrastructure\Services\Stats\PlatformStatsProviderResolver;
use Illuminate\Support\ServiceProvider;

class MarketingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(SocialAccountRepositoryInterface::class, SocialAccountRepository::class);
        $this->app->bind(SocialPostRepositoryInterface::class, SocialPostRepository::class);
        $this->app->bind(MarketingLeadRepositoryInterface::class, MarketingLeadRepository::class);

        // Issue #1433 — publishers reseaux sociaux (LinkedIn/Meta/X), tous
        // routes via l'agregateur Ayrshare (voir AbstractAyrsharePublisher).
        // SocialPublishingService::publishNow() resout le publisher par
        // plateforme cible via SocialPublisherResolver.
        $this->app->singleton(SocialPublisherResolver::class, function ($app): SocialPublisherResolver {
            return new SocialPublisherResolver([
                $app->make(LinkedInPublisher::class),
                $app->make(MetaPublisher::class),
                $app->make(TwitterPublisher::class),
            ]);
        });

        // Marketing OS — stockage des binaires de la mediatheque (disk prive,
        // hors webroot) et decision « publication directe ou assistee ».
        // Aucun binaire n'est jamais ecrit en base : voir la migration
        // `2026_10_15_000100_create_marketing_os_tables.php`.
        $this->app->singleton(MarketingAssetStorage::class);
        $this->app->singleton(MarketingOsDashboardService::class);
        $this->app->singleton(MarketingPublicationModeResolver::class);

        // #8530 — statistiques REELLES des plateformes. Le resolver retourne
        // `null` (et non une exception) quand aucune plateforme ne sait
        // fournir la donnee : le service la declare alors indisponible au
        // lieu d'inventer une valeur. YouTube/TikTok ne sont pas couverts par
        // l'agregateur (extension suivie par #8532).
        $this->app->singleton(PlatformStatsProviderResolver::class, function ($app): PlatformStatsProviderResolver {
            return new PlatformStatsProviderResolver([
                $app->make(AyrshareAnalyticsProvider::class),
            ]);
        });

        $this->app->singleton(MarketingPublicationStatsService::class);

        // #8532 — capacites de publication directe par plateforme : source de
        // verite unique de l'affirmation « direct » vs « assiste ».
        $this->app->singleton(PlatformPublishingCapability::class);
    }

    public function boot(): void
    {
        // Routes chargees via require dans routes/api.php (Phase 3) :
        // routes/modules/marketing.php.
    }
}
