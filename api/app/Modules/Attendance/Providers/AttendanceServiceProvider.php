<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Providers;

use App\Modules\Attendance\Domain\Contracts\GeofenceValidatorInterface;
use App\Modules\Attendance\Infrastructure\Services\AttendanceGeofenceService;
use Illuminate\Support\ServiceProvider;

class AttendanceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // ADR-0016 Phase 5 (#5356) : binding historiquement porté par le
        // provider de l'ancien module SmartAttendance (supprimé) — le contrat
        // vit désormais dans le module Attendance (module unique après fusion).
        $this->app->bind(
            GeofenceValidatorInterface::class,
            AttendanceGeofenceService::class,
        );
    }

    public function boot(): void
    {
        // ADR-0016 Phase 3 (#5354) : routes géo consolidées sous /api/v1/attendance/*
        // (Phase 5 #5356 : alias /smart-attendance/* supprimés, contrat unique).
        $this->loadRoutesFrom(__DIR__.'/../routes/geo.php');
    }
}
