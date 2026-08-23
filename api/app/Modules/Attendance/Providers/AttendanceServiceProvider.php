<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Providers;

use Illuminate\Support\ServiceProvider;

class AttendanceServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        // ADR-0016 Phase 3 (#5354) : routes géo consolidées sous /api/v1/attendance/*
        // (les alias /smart-attendance/* restent chargés par SmartAttendanceServiceProvider).
        $this->loadRoutesFrom(__DIR__.'/../routes/geo.php');
    }
}
