<?php

declare(strict_types=1);

namespace App\Modules\Marketing\Providers;

use App\Modules\Marketing\Domain\Contracts\MarketingLeadRepositoryInterface;
use App\Modules\Marketing\Domain\Contracts\SocialAccountRepositoryInterface;
use App\Modules\Marketing\Domain\Contracts\SocialPostRepositoryInterface;
use App\Modules\Marketing\Infrastructure\Repositories\MarketingLeadRepository;
use App\Modules\Marketing\Infrastructure\Repositories\SocialAccountRepository;
use App\Modules\Marketing\Infrastructure\Repositories\SocialPostRepository;
use App\Modules\Marketing\Infrastructure\Services\Publishers\LinkedInPublisher;
use App\Modules\Marketing\Infrastructure\Services\Publishers\MetaPublisher;
use App\Modules\Marketing\Infrastructure\Services\Publishers\SocialPublisherResolver;
use App\Modules\Marketing\Infrastructure\Services\Publishers\TwitterPublisher;
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
    }

    public function boot(): void
    {
        // Routes chargees via require dans routes/api.php (Phase 3) :
        // routes/modules/marketing.php.
    }
}
