<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\HealthManager\Domain\Access\HealthAccess;
use App\Modules\HealthManager\Domain\Models\HealthConsultation;

/**
 * HC-005 (#7789) — Policy des consultations (BC-30), deny-by-default.
 *
 * CONTENU MÉDICAL : praticiens actifs et direction (`health.admin`) lisent ;
 * seul le praticien AUTEUR (ou la direction) modifie SA consultation ;
 * la réception (`health.reception`) et la facturation n'accèdent JAMAIS
 * au dossier médical (critère d'acceptation HC-005 : réception → 403).
 */
class HealthConsultationPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return HealthAccess::canViewMedicalRecords($actor);
    }

    public function view(Employee $actor, HealthConsultation $consultation): bool
    {
        return $this->viewAny($actor) && $consultation->company_id === $actor->company_id;
    }

    public function create(Employee $actor): bool
    {
        return HealthAccess::canViewMedicalRecords($actor);
    }

    /**
     * Seul le praticien AUTEUR — ou la direction — modifie une
     * consultation (critère d'acceptation HC-005).
     */
    public function update(Employee $actor, HealthConsultation $consultation): bool
    {
        if ($consultation->company_id !== $actor->company_id) {
            return false;
        }

        if (HealthAccess::isAdmin($actor)) {
            return true;
        }

        return HealthAccess::practitionerId($actor) === $consultation->practitioner_id;
    }
}
