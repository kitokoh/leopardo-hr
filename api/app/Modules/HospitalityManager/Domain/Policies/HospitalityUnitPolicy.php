<?php

declare(strict_types=1);

namespace App\Modules\HospitalityManager\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\HospitalityManager\Domain\Models\HospitalityUnit;
use App\Modules\HospitalityManager\Domain\Policies\Concerns\ChecksHospitalityPropertyAccess;

/**
 * HOSP-002 (#7944) / HOSP-003 (#7945, BC-32) — Policy des unités physiques.
 *
 * Autorisation portée par l'établissement parent (`property_id`) : lecture
 * = `view`, gestion = `manage` (scoping progressif — voir le trait).
 * Cross-tenant → refus (fail-closed).
 */
class HospitalityUnitPolicy
{
    use ChecksHospitalityPropertyAccess;

    public function viewAny(Employee $actor): bool
    {
        return $this->canViewPropertyResource($actor, null);
    }

    public function view(Employee $actor, HospitalityUnit $unit): bool
    {
        return $unit->company_id === $actor->company_id
            && $this->canViewPropertyResource($actor, $unit->getAttribute('property_id'));
    }

    /**
     * Création sous un établissement donné (route imbriquée) : gestion de
     * CET établissement requise.
     */
    public function create(Employee $actor, int $propertyId): bool
    {
        return $this->canManagePropertyResource($actor, $propertyId);
    }

    public function update(Employee $actor, HospitalityUnit $unit): bool
    {
        return $unit->company_id === $actor->company_id
            && $this->canManagePropertyResource($actor, $unit->getAttribute('property_id'));
    }

    public function delete(Employee $actor, HospitalityUnit $unit): bool
    {
        return $this->update($actor, $unit);
    }
}
