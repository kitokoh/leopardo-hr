<?php

declare(strict_types=1);

namespace App\Modules\Absence\Providers;

use Illuminate\Support\ServiceProvider;

class AbsenceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bind Absence module contracts here
    }

    public function boot(): void
    {
        // Boot Absence module — routes loaded via routes/modules/absence.php
    }
}
