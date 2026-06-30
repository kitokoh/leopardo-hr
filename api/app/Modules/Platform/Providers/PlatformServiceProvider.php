<?php

declare(strict_types=1);

namespace App\Modules\Platform\Providers;

use Illuminate\Support\ServiceProvider;

class PlatformServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bind Platform module contracts here
    }

    public function boot(): void
    {
        // Boot Platform module
    }
}
