<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\HealthManager\Domain\Access\HealthAccess;
use App\Modules\HealthManager\Domain\Models\HealthAdmission;

/**
 * HC-006 (#7790) — Policy des hospitalisations (BC-30), deny-by-default.
 *
 * Direction et accueil (admissions) gèrent le séjour de bout en bout
 * (admission, transfert, sortie) ; les praticiens actifs consultent les
 * séjours et l'occupation ; employé lambda et facturation : 403.
 */
class HealthAdmissionPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return HealthAccess::canViewAppointments($actor);
    }

    public function view(Employee $actor, HealthAdmission $admission): bool
    {
        return $this->viewAny($actor) && $admission->company_id === $actor->company_id;
    }

    public function create(Employee $actor): bool
    {
        return HealthAccess::canManageAppointments($actor);
    }

    public function update(Employee $actor, HealthAdmission $admission): bool
    {
        return $this->create($actor) && $admission->company_id === $actor->company_id;
    }

    /**
     * Transfert de lit et sortie : mêmes gestionnaires que l'admission.
     */
    public function transition(Employee $actor, HealthAdmission $admission): bool
    {
        return $this->update($actor, $admission);
    }
}
