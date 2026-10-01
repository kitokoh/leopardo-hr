<?php

declare(strict_types=1);

namespace App\Modules\Fleet\Application\Actions;

use App\Modules\Fleet\Domain\Models\Vehicle;
use App\Shared\Contracts\Tracking\VehicleTrackingProvider;

/**
 * Cas d'usage « carte temps reel de la flotte » (BOS-024g, #8218).
 *
 * Extrait de `FleetController::liveMap` : vehicules actifs equipes d'un
 * traceur, puis position courante. Issue #3148 — un SEUL appel Traccar
 * agrege (deviceId=1,2,3...) remplace un appel HTTP par vehicule : ce
 * contrat de performance est l'invariant porte par l'Action, pas un detail
 * d'affichage.
 */
final class BuildFleetLiveMapAction
{
    public function __construct(private readonly VehicleTrackingProvider $traccar) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function execute(string $companyId): array
    {
        $vehicles = Vehicle::query()
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->whereNotNull('traccar_device_id')
            ->select(['id', 'plate_number', 'brand', 'model', 'type', 'traccar_device_id', 'assigned_driver_id'])
            ->get();

        $positionsByDevice = $this->traccar->getLastPositions(
            array_values($vehicles->pluck('traccar_device_id')->filter()->map(fn ($id): int => (int) $id)->all())
        );

        $positions = [];

        foreach ($vehicles as $vehicle) {
            $positions[] = [
                'vehicle_id' => $vehicle->id,
                'plate_number' => $vehicle->plate_number,
                'brand' => $vehicle->brand,
                'model' => $vehicle->model,
                'type' => $vehicle->type,
                'position' => $positionsByDevice[(int) $vehicle->traccar_device_id] ?? null,
            ];
        }

        return $positions;
    }
}
