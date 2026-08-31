<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\EduManager\Domain\Access\EduAccess;
use App\Modules\EduManager\Domain\Models\EduAdmission;

/**
 * #5825 (EDU-009) — admissions : direction uniquement (dossiers contenant
 * des PII d'enfants et de responsables légaux).
 */
class EduAdmissionPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return EduAccess::isAdmin($actor);
    }

    public function view(Employee $actor, EduAdmission $admission): bool
    {
        return $admission->company_id === $actor->company_id && EduAccess::isAdmin($actor);
    }

    public function create(Employee $actor): bool
    {
        return EduAccess::isAdmin($actor);
    }

    public function update(Employee $actor, EduAdmission $admission): bool
    {
        return $this->view($actor, $admission);
    }

    public function delete(Employee $actor, EduAdmission $admission): bool
    {
        return $this->view($actor, $admission);
    }

    public function convert(Employee $actor, EduAdmission $admission): bool
    {
        return $this->view($actor, $admission);
    }

    /**
     * #5831 (EDU-015) — relance marketing consentie : direction uniquement.
     */
    public function followUp(Employee $actor, EduAdmission $admission): bool
    {
        return $this->view($actor, $admission);
    }

    /**
     * #5831 (EDU-015) — opt-out RGPD : direction uniquement.
     */
    public function optOut(Employee $actor, EduAdmission $admission): bool
    {
        return $this->view($actor, $admission);
    }
}
