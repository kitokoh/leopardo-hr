<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\HealthManager\Domain\Access\HealthAccess;
use App\Modules\HealthManager\Domain\Models${model};

/**
 * HC-002 (#7786) — Policy lit (structure clinique, BC-30).
 *
 * Deny-by-default : la direction (health.admin) gère ; l'accueil
 * (health.reception) et les praticiens actifs (health.practitioner)
 * lisent ; un employé lambda n'accède à rien (403).
 */
class HealthBedPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return HealthAccess::canViewStructure($actor);
    }

    public function view(Employee $actor, HealthBed $resource): bool
    {
        return $this->viewAny($actor) && $resource->company_id === $actor->company_id;
    }

    public function create(Employee $actor): bool
    {
        return HealthAccess::canManageStructure($actor);
    }

    public function update(Employee $actor, HealthBed $resource): bool
    {
        return $this->create($actor) && $resource->company_id === $actor->company_id;
    }

    public function delete(Employee $actor, HealthBed $resource): bool
    {
        return $this->update($actor, $resource);
    }
}
