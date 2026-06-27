<?php

namespace App\Modules\Fleet\Infrastructure\Services;

use App\Modules\Fleet\Domain\Models\Vehicle;
use App\Modules\Fleet\Domain\Models\VehicleAssignment;
use App\Modules\Fleet\Domain\Models\VehicleTrip;

class FleetService
{
    /**
     * Assign a vehicle to an employee.
     */
    public function assignVehicle(Vehicle $vehicle, string $employeeId, string $startDate, ?string $endDate = null): VehicleAssignment
    {
        return VehicleAssignment::query()->create([
            'vehicle_id' => $vehicle->id,
            'employee_id' => $employeeId,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);
    }

    /**
     * Log a vehicle trip.
     */
    public function logTrip(Vehicle $vehicle, array $tripData): VehicleTrip
    {
        return VehicleTrip::query()->create(array_merge(
            $tripData,
            ['vehicle_id' => $vehicle->id]
        ));
    }

    /**
     * Get available vehicles for a company.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Vehicle>
     */
    public function getAvailableVehicles(string $companyId): \Illuminate\Database\Eloquent\Collection
    {
        return Vehicle::query()
            ->where('company_id', $companyId)
            ->where('status', 'available')
            ->get();
    }
}
