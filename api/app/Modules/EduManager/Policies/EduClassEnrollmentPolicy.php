<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\EduManager\Domain\Access\EduAccess;
use App\Modules\EduManager\Domain\Models\EduClassEnrollment;

/**
 * #5827 (EDU-011) — inscriptions : la direction inscrit/désinscrit.
 *
 * La consultation des effectifs d'une classe passe par EduClassPolicy
 * (admin ou enseignant de SES classes) ; la désinscription exige la
 * direction (EduAccess::isAdmin). Cross-tenant → false (fail-closed).
 */
class EduClassEnrollmentPolicy
{
    public function delete(Employee $actor, EduClassEnrollment $enrollment): bool
    {
        return $enrollment->company_id === $actor->company_id && EduAccess::isAdmin($actor);
    }
}
