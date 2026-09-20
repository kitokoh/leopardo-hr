<?php

declare(strict_types=1);

namespace App\Modules\HospitalityManager\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\HospitalityManager\Domain\Access\HospitalityAccess;
use App\Modules\HospitalityManager\Domain\Models\HospitalityRoomType;

/**
 * HOSP-002 (#7944, BC-32) — Policy des types de chambres.
 *
 * Même matrice que l'établissement parent (deny-by-default, direction
 * uniquement) ; cross-tenant → refus (fail-closed).
 */
class HospitalityRoomTypePolicy
{
    public function viewAny(Employee $actor): bool
    {
        return HospitalityAccess::isAdmin($actor);
    }

    public function view(Employee $actor, HospitalityRoomType $roomType): bool
    {
        return $roomType->company_id === $actor->company_id && $this->viewAny($actor);
    }

    public function create(Employee $actor): bool
    {
        return HospitalityAccess::isAdmin($actor);
    }

    public function update(Employee $actor, HospitalityRoomType $roomType): bool
    {
        return $roomType->company_id === $actor->company_id && HospitalityAccess::isAdmin($actor);
    }

    public function delete(Employee $actor, HospitalityRoomType $roomType): bool
    {
        return $this->update($actor, $roomType);
    }
}
