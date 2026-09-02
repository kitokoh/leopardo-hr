<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\EduManager\Domain\Models\EduAdmission;

/**
 * #5820 (EDU-004) — Policy des dossiers d'inscription (admissions).
 *
 * V0 : les rôles de gestion du tenant (principal, rh, manager) gèrent les
 * dossiers. `convert` (conversion dossier → élève) suit la même règle :
 * l'acte crée un élève du TENANT courant, il est donc borné au tenant
 * comme view/update/delete. Les permissions fines du manifest
 * (`edu.admin`/`edu.teacher`/`edu.guardian`) seront câblées avec l'API
 * EduManager (EDU-006/EDU-010).
 */
class EduAdmissionPolicy
{
    public const MANAGER_ROLES = ['principal', 'rh', 'manager'];

    public function viewAny(Employee $actor): bool
    {
        return $actor->hasManagerRole(...self::MANAGER_ROLES);
    }

    public function view(Employee $actor, EduAdmission $admission): bool
    {
        return $this->viewAny($actor) && $admission->company_id === $actor->company_id;
    }

    public function create(Employee $actor): bool
    {
        return $this->viewAny($actor);
    }

    public function update(Employee $actor, EduAdmission $admission): bool
    {
        return $this->view($actor, $admission);
    }

    public function delete(Employee $actor, EduAdmission $admission): bool
    {
        return $this->view($actor, $admission);
    }

    /**
     * Conversion dossier → élève (AdmissionService::convert) : réservé aux
     * gestionnaires du tenant — la conversion crée un élève, elle ne doit
     * jamais être déclenchée sur le dossier d'un autre tenant.
     */
    public function convert(Employee $actor, EduAdmission $admission): bool
    {
        return $this->viewAny($actor) && $admission->company_id === $actor->company_id;
    }
}
