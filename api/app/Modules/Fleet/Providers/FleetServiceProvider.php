<?php

declare(strict_types=1);

namespace App\Modules\Fleet\Providers;

use App\Modules\Fleet\Infrastructure\Services\FleetTrackingSyncService;
use App\Shared\Contracts\Fleet\FleetTrackingSynchronizer;
use Illuminate\Support\ServiceProvider;

class FleetServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // BOS-023 cycle Fleet↔Attendance (#8298) — contrat partagé de
        // synchronisation : les modules consommateurs (Attendance,
        // `TrackingSyncController`) ne dépendent plus de
        // `FleetTrackingSyncService` directement, uniquement de l'interface
        // Shared.
        $this->app->bind(FleetTrackingSynchronizer::class, FleetTrackingSyncService::class);
    }

    public function boot(): void {}
}
