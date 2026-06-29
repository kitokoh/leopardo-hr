<?php

declare(strict_types=1);

namespace App\Modules\Fleet\Domain\Contracts;

use App\Modules\Fleet\Domain\Models\VehicleTrip;
use Illuminate\Support\Collection;

interface TripRepositoryInterface
{
    public function findById(int $id): ?VehicleTrip;

    /** @return Collection<int, VehicleTrip> */
    public function findByVehicle(int $vehicleId): Collection;

    public function save(VehicleTrip $trip): VehicleTrip;
}
