<?php

declare(strict_types=1);

namespace App\Modules\HospitalityManager\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\HospitalityManager\Domain\Models\HospitalityPropertyStaff;
use App\Modules\HospitalityManager\Domain\Policies\Concerns\ChecksHospitalityPropertyAccess;

/**
 * HOSP-003 (#7945, BC-32) — Policy des affectations staff ↔ établissement.
 *
 * Autorisation portée par l'établissement (`property_id`) : consultation de
 * l'équipe = `view`, gestion des affectations = `manage` (scoping
 * progressif — voir le trait). Rappel spec §4 : le `role` du pivot est
 * descriptif, jamais une source d'autorisation. Cross-tenant → refus.
 */
class HospitalityPropertyStaffPolicy
{
    use ChecksHospitalityPropertyAccess;

    public function viewAny(Employee $actor): bool
    {
        return $this->canViewPropertyResource($actor, null);
    }

    public function view(Employee $actor, HospitalityPropertyStaff $assignment): bool
    {
        return $assignment->company_id === $actor->company_id
            && $this->canViewPropertyResource($actor, $assignment->getAttribute('property_id'));
    }

    /**
     * Affectation sous un établissement donné : gestion de CET
     * établissement requise.
     */
    public function create(Employee $actor, int $propertyId): bool
    {
        return $this->canManagePropertyResource($actor, $propertyId);
    }

    public function update(Employee $actor, HospitalityPropertyStaff $assignment): bool
    {
        return $assignment->company_id === $actor->company_id
            && $this->canManagePropertyResource($actor, $assignment->getAttribute('property_id'));
    }

    public function delete(Employee $actor, HospitalityPropertyStaff $assignment): bool
    {
        return $this->update($actor, $assignment);
    }
}
