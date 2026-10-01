<?php

declare(strict_types=1);

namespace Tests\Unit\Fleet;

use App\Modules\Attendance\Infrastructure\Services\TraccarService;
use App\Modules\Fleet\Infrastructure\Services\FleetTrackingSyncService;
use App\Shared\Contracts\Fleet\FleetTrackingSynchronizer;
use App\Shared\Contracts\Tracking\VehicleTrackingProvider;
use Tests\TestCase;

/**
 * BOS-023 cycle Fleet↔Attendance (#8298) — contrats partagés de tracking :
 * le container résout `VehicleTrackingProvider` vers `TraccarService`
 * (Attendance) et `FleetTrackingSynchronizer` vers `FleetTrackingSyncService`
 * (Fleet), ce qui supprime les imports croisés `Fleet -> Attendance` et
 * `Attendance -> Fleet` (allowlist d'isolation #5584, chantier #8211).
 */
class FleetTrackingContractsTest extends TestCase
{
    public function test_vehicle_tracking_provider_resolves_to_traccar_service(): void
    {
        $provider = $this->app->make(VehicleTrackingProvider::class);

        $this->assertInstanceOf(TraccarService::class, $provider);
        $this->assertInstanceOf(VehicleTrackingProvider::class, $provider);
    }

    public function test_fleet_tracking_synchronizer_resolves_to_fleet_service(): void
    {
        $synchronizer = $this->app->make(FleetTrackingSynchronizer::class);

        $this->assertInstanceOf(FleetTrackingSyncService::class, $synchronizer);
        $this->assertInstanceOf(FleetTrackingSynchronizer::class, $synchronizer);
    }

    public function test_fleet_sync_service_is_injectable_via_tracking_contract(): void
    {
        // FleetTrackingSyncService ne dépend plus de `TraccarService` concret :
        // sa dépendance est résolue via le contrat Shared — preuve que le
        // binding Attendance suffit à l'instancier hors de tout import croisé.
        $service = $this->app->make(FleetTrackingSyncService::class);

        $this->assertInstanceOf(FleetTrackingSynchronizer::class, $service);
    }

    public function test_max_window_days_contract_constant_is_preserved(): void
    {
        // Contrat historique #3369 : fenêtre maximale de 90 jours, inchangée
        // pour les appelants (`FleetSyncCommand`, `TrackingSyncController`).
        $this->assertSame(90, FleetTrackingSynchronizer::MAX_WINDOW_DAYS);
        $this->assertSame(
            FleetTrackingSynchronizer::MAX_WINDOW_DAYS,
            FleetTrackingSyncService::MAX_WINDOW_DAYS,
        );
    }
}
