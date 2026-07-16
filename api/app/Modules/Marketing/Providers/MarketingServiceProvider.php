<?php

declare(strict_types=1);

namespace App\Modules\Marketing\Providers;

use Illuminate\Support\ServiceProvider;

class MarketingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bind Marketing module contracts here (Phase 2: SocialAccountRepositoryInterface).
    }

    public function boot(): void
    {
        // Boot Marketing module — routes loaded via routes/modules/marketing.php (Phase 3).
    }
}
