<?php

declare(strict_types=1);

namespace App\Modules\Growth\Providers;

use Illuminate\Support\ServiceProvider;

class GrowthServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bind Growth module contracts here
    }

    public function boot(): void
    {
        // Boot Growth module — routes loaded via routes/modules/growth.php
    }
}
