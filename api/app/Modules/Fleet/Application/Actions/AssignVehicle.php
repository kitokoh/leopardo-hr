<?php

declare(strict_types=1);

namespace App\Modules\Fleet\Application\Actions;

use App\Modules\Fleet\Domain\Models\Vehicle;
use App\Modules\Fleet\Domain\Models\VehicleAssignment;
use App\Modules\Fleet\Infrastructure\Services\FleetService;

class AssignVehicle
{
    public function __construct(
        private readonly FleetService $fleetService,
    ) {}

    public function handle(string $vehicleId, string $employeeId, string $startDate, ?string $endDate = null): VehicleAssignment
    {
        $vehicle = Vehicle::query()->findOrFail($vehicleId);

        return $this->fleetService->assignVehicle($vehicle, $employeeId, $startDate, $endDate);
    }
}
