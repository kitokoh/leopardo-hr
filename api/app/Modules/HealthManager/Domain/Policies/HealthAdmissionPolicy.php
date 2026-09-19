<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\HealthManager\Domain\Access\HealthAccess;
use App\Modules\HealthManager\Domain\Models\HealthAdmission;

/**
 * #7790 (BC-30) — Policy des hospitalisations (admissions).
 *
 * Deny-by-default (spec §2) : direction et réception gèrent les admissions
 * (sans JAMAIS accéder au contenu médical) ; praticien en LECTURE ;
 * facturation et employé lambda refusés. Cross-tenant → refus (fail-closed).
 */
class HealthAdmissionPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return HealthAccess::isAdmin($actor)
            || HealthAccess::isReception($actor)
            || HealthAccess::isPractitioner($actor);
    }

    public function view(Employee $actor, HealthAdmission $admission): bool
    {
        return $admission->company_id === $actor->company_id && $this->viewAny($actor);
    }

    public function create(Employee $actor): bool
    {
        return HealthAccess::isAdmin($actor) || HealthAccess::isReception($actor);
    }

    public function update(Employee $actor, HealthAdmission $admission): bool
    {
        return $admission->company_id === $actor->company_id
            && (HealthAccess::isAdmin($actor) || HealthAccess::isReception($actor));
    }

    public function delete(Employee $actor, HealthAdmission $admission): bool
    {
        return $this->update($actor, $admission);
    }
}
