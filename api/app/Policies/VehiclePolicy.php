<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Employee;
use App\Models\Vehicle;

class VehiclePolicy
{
    public function viewAny(Employee $actor): bool
    {
        return $actor->isManager();
    }

    public function view(Employee $actor, Vehicle $vehicle): bool
    {
        return $actor->company_id === $vehicle->company_id && $actor->isManager();
    }

    public function create(Employee $actor): bool
    {
        return $actor->isManager();
    }

    public function update(Employee $actor, Vehicle $vehicle): bool
    {
        return $actor->company_id === $vehicle->company_id && $actor->isManager();
    }

    public function delete(Employee $actor, Vehicle $vehicle): bool
    {
        return $actor->company_id === $vehicle->company_id && $actor->hasManagerRole('principal');
    }

    public function assignDriver(Employee $actor, Vehicle $vehicle): bool
    {
        return $actor->company_id === $vehicle->company_id && $actor->isManager();
    }
}
