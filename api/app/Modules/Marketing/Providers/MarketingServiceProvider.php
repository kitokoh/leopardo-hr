<?php

declare(strict_types=1);

namespace App\Modules\Marketing\Providers;

use App\Modules\Marketing\Domain\Contracts\SocialAccountRepositoryInterface;
use App\Modules\Marketing\Domain\Contracts\SocialPostRepositoryInterface;
use App\Modules\Marketing\Infrastructure\Repositories\SocialAccountRepository;
use App\Modules\Marketing\Infrastructure\Repositories\SocialPostRepository;
use Illuminate\Support\ServiceProvider;

class MarketingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(SocialAccountRepositoryInterface::class, SocialAccountRepository::class);
        $this->app->bind(SocialPostRepositoryInterface::class, SocialPostRepository::class);
    }

    public function boot(): void
    {
        // Boot Marketing module — routes loaded via routes/modules/marketing.php (Phase 3).
    }
}
