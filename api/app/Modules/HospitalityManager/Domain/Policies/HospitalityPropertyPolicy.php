<?php

declare(strict_types=1);

namespace App\Modules\HospitalityManager\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\HospitalityManager\Domain\Models\HospitalityProperty;
use App\Modules\HospitalityManager\Domain\Policies\Concerns\ChecksHospitalityPropertyAccess;

/**
 * HOSP-002 (#7944) / HOSP-003 (#7945, BC-32) — Policy des établissements.
 *
 * Deny-by-default (spec §4) avec RBAC ressource-scopé progressif : tant
 * qu'aucune assignation `hospitality_property` n'existe dans le tenant,
 * fallback direction (`principal`/`rh`) ; dès la première assignation,
 * fail-closed via `Employee::hasResourceAccess()` (un non-assigné ne lit
 * plus, `rh` retombe à la lecture seule, création d'établissement =
 * company-wide → `principal`). Cross-tenant → refus (fail-closed).
 */
class HospitalityPropertyPolicy
{
    use ChecksHospitalityPropertyAccess;

    public function viewAny(Employee $actor): bool
    {
        return $this->canViewPropertyResource($actor, null);
    }

    public function view(Employee $actor, HospitalityProperty $property): bool
    {
        return $property->company_id === $actor->company_id
            && $this->canViewPropertyResource($actor, $property->getKey());
    }

    public function create(Employee $actor): bool
    {
        // Créer un établissement = geste company-wide (pas encore de site) :
        // `manage` sur ressource nulle → principal dès que le scoping est
        // actif, direction (principal/rh) avant la première assignation.
        return $this->canManagePropertyResource($actor, null);
    }

    public function update(Employee $actor, HospitalityProperty $property): bool
    {
        return $property->company_id === $actor->company_id
            && $this->canManagePropertyResource($actor, $property->getKey());
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
