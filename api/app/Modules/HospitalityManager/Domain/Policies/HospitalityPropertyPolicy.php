<?php

declare(strict_types=1);

namespace App\Modules\HospitalityManager\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\HospitalityManager\Domain\Access\HospitalityAccess;
use App\Modules\HospitalityManager\Domain\Models\HospitalityProperty;

/**
 * HOSP-002 (#7944, BC-32) — Policy des établissements (référentiel).
 *
 * Deny-by-default (spec §4) : gestion réservée à la direction ; lecture
 * réservée à la direction tant que le RBAC ressource-scopé par
 * établissement n'est pas branché (HOSP-003 #7945). Cross-tenant → refus
 * (fail-closed).
 */
class HospitalityPropertyPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return HospitalityAccess::isAdmin($actor);
    }

    public function view(Employee $actor, HospitalityProperty $property): bool
    {
        return $property->company_id === $actor->company_id && $this->viewAny($actor);
    }

    public function create(Employee $actor): bool
    {
        return HospitalityAccess::isAdmin($actor);
    }

    public function update(Employee $actor, HospitalityProperty $property): bool
    {
        return $property->company_id === $actor->company_id && HospitalityAccess::isAdmin($actor);
    }

    public function delete(Employee $actor, HospitalityProperty $property): bool
    {
        return $this->update($actor, $property);
    }

    /**
     * Publication / dépublication de la vitrine publique (/stay).
     */
    public function publish(Employee $actor, HospitalityProperty $property): bool
    {
        return $this->update($actor, $property);
    }
}
