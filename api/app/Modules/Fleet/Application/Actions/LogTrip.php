<?php

namespace App\Modules\Fleet\Application\Actions;

use App\Modules\Fleet\Domain\Models\Vehicle;
use App\Modules\Fleet\Domain\Models\VehicleTrip;
use App\Modules\Fleet\Infrastructure\Services\FleetService;

class LogTrip
{
    public function __construct(
        private readonly FleetService $fleetService,
    ) {}

    public function handle(string $vehicleId, array $tripData): VehicleTrip
    {
        $vehicle = Vehicle::query()->findOrFail($vehicleId);

        return $this->fleetService->logTrip($vehicle, $tripData);
    }
}
