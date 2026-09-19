<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\HealthManager\Domain\Access\HealthAccess;
use App\Modules\HealthManager\Domain\Models\HealthPatient;

/**
 * #7787 (BC-30) — Policy des patients.
 *
 * Deny-by-default (spec §2) : gestion administrative par la direction et la
 * réception ; praticien en LECTURE seule ; facturation et employé lambda
 * refusés. Cross-tenant → refus (fail-closed). Le contenu médical du
 * dossier (consultations, prescriptions) relève de policies distinctes.
 */
class HealthPatientPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return HealthAccess::isAdmin($actor)
            || HealthAccess::isReception($actor)
            || HealthAccess::isPractitioner($actor);
    }

    public function view(Employee $actor, HealthPatient $patient): bool
    {
        return $patient->company_id === $actor->company_id && $this->viewAny($actor);
    }

    public function create(Employee $actor): bool
    {
        return HealthAccess::isAdmin($actor) || HealthAccess::isReception($actor);
    }

    public function update(Employee $actor, HealthPatient $patient): bool
    {
        return $patient->company_id === $actor->company_id
            && (HealthAccess::isAdmin($actor) || HealthAccess::isReception($actor));
    }

    public function delete(Employee $actor, HealthPatient $patient): bool
    {
        // Jamais de suppression physique (spec §3) — l'archivage passe par
        // `status`, autorisé au même périmètre que la mise à jour.
        return $this->update($actor, $patient);
    }
}
