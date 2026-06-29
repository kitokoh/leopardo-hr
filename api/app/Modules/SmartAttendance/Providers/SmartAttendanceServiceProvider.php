<?php

declare(strict_types=1);

namespace App\Modules\SmartAttendance\Providers;

use App\Modules\Attendance\Infrastructure\Services\AttendanceGeofenceService;
use App\Modules\SmartAttendance\Domain\Contracts\GeofenceValidatorInterface;
use Illuminate\Support\ServiceProvider;

class SmartAttendanceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bind le contrat vers l'implémentation existante (réutilisation, pas de duplication)
        $this->app->bind(
            GeofenceValidatorInterface::class,
            AttendanceGeofenceService::class,
        );
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../routes/smart_attendance.php');
    }
}
