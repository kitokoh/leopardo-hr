<?php

declare(strict_types=1);

namespace App\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Auth\Domain\Policies\Concerns\ChecksResourceScopedAccess;
use App\Modules\Fleet\Domain\Models\Vehicle;

/**
 * #7600 (R3 de l'épique #7597) — la flotte devient ressource-scopée :
 * historiquement « tout manager voit tout véhicule » ; dès qu'une assignation
 * `vehicle` existe dans l'entreprise, la lecture et la gestion sont bornées
 * aux véhicules assignés (`view`/`manage`), le comportement historique étant
 * conservé avant la première assignation (progressivité #7598).
 */
class VehiclePolicy
{
    use ChecksResourceScopedAccess;

    public function viewAny(Employee $actor): bool
    {
        return $actor->isManager() || $actor->isResourceTypeScoped('vehicle');
    }

    public function view(Employee $actor, Vehicle $vehicle): bool
    {
        return $actor->company_id === $vehicle->company_id
            && $this->canViewScopedResource($actor, 'vehicle', $vehicle->id, $actor->isManager());
    }

    public function create(Employee $actor): bool
    {
        return $this->canManageScopedResource($actor, 'vehicle', null, $actor->isManager());
    }

    public function update(Employee $actor, Vehicle $vehicle): bool
    {
        return $actor->company_id === $vehicle->company_id
            && $this->canManageScopedResource($actor, 'vehicle', $vehicle->id, $actor->isManager());
    }

    public function delete(Employee $actor, Vehicle $vehicle): bool
    {
        return $actor->company_id === $vehicle->company_id && $actor->hasManagerRole('principal');
    }

    public function assignDriver(Employee $actor, Vehicle $vehicle): bool
    {
        return $actor->company_id === $vehicle->company_id
            && $this->canManageScopedResource($actor, 'vehicle', $vehicle->id, $actor->isManager());
    }
}
