<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\HealthManager\Domain\Access\HealthAccess;
use App\Modules\HealthManager\Domain\Models\HealthPatient;

/**
 * HC-003 (#7787) — Policy du registre patients (BC-30).
 *
 * RBAC STRICT deny-by-default (données de santé, art. 9 RGPD) :
 * `health.admin` (direction) et `health.reception` (accueil) gèrent ;
 * `health.practitioner` (praticien actif) et `health.billing` lisent ;
 * un employé lambda n'accède à RIEN (403).
 *
 * `delete` = ARCHIVAGE (statut archived + soft delete) — jamais de
 * suppression physique.
 */
class HealthPatientPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return HealthAccess::canViewPatients($actor);
    }

    public function view(Employee $actor, HealthPatient $patient): bool
    {
        return $this->viewAny($actor) && $patient->company_id === $actor->company_id;
    }

    public function create(Employee $actor): bool
    {
        return HealthAccess::canManagePatients($actor);
    }

    public function update(Employee $actor, HealthPatient $patient): bool
    {
        return $this->create($actor) && $patient->company_id === $actor->company_id;
    }

    public function delete(Employee $actor, HealthPatient $patient): bool
    {
        return $this->update($actor, $patient);
    }
}
