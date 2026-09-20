<?php

declare(strict_types=1);

namespace App\Modules\HospitalityManager\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\HospitalityManager\Domain\Access\HospitalityAccess;
use App\Modules\HospitalityManager\Domain\Models\HospitalityUnit;

/**
 * HOSP-002 (#7944, BC-32) — Policy des unités physiques (chambres /
 * appartements).
 *
 * Même matrice que l'établissement parent (deny-by-default, direction
 * uniquement) ; cross-tenant → refus (fail-closed).
 */
class HospitalityUnitPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return HospitalityAccess::isAdmin($actor);
    }

    public function view(Employee $actor, HospitalityUnit $unit): bool
    {
        return $unit->company_id === $actor->company_id && $this->viewAny($actor);
    }

    public function create(Employee $actor): bool
    {
        return HospitalityAccess::isAdmin($actor);
    }

    public function update(Employee $actor, HospitalityUnit $unit): bool
    {
        return $unit->company_id === $actor->company_id && HospitalityAccess::isAdmin($actor);
    }

    public function delete(Employee $actor, HospitalityUnit $unit): bool
    {
        return $this->update($actor, $unit);
    }
}
