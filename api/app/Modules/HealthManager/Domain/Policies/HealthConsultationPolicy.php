<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\HealthManager\Domain\Access\HealthAccess;
use App\Modules\HealthManager\Domain\Models\HealthConsultation;

/**
 * #7789 (BC-30) — Policy des consultations (contenu MÉDICAL).
 *
 * Deny-by-default (spec §2) : contenu médical visible des praticiens et de
 * la direction UNIQUEMENT — réception et facturation TOUJOURS refusées.
 * Création par un praticien ; mise à jour par l'AUTEUR (sa fiche
 * praticien) ou la direction. Cross-tenant → refus (fail-closed).
 */
class HealthConsultationPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return HealthAccess::isAdmin($actor) || HealthAccess::isPractitioner($actor);
    }

    public function view(Employee $actor, HealthConsultation $consultation): bool
    {
        return $consultation->company_id === $actor->company_id && $this->viewAny($actor);
    }

    public function create(Employee $actor): bool
    {
        return HealthAccess::isAdmin($actor) || HealthAccess::isPractitioner($actor);
    }

    public function update(Employee $actor, HealthConsultation $consultation): bool
    {
        if ($consultation->company_id !== $actor->company_id) {
            return false;
        }

        if (HealthAccess::isAdmin($actor)) {
            return true;
        }

        $practitionerId = HealthAccess::practitionerId($actor);

        return $practitionerId !== null && $consultation->practitioner_id === $practitionerId;
    }

    public function delete(Employee $actor, HealthConsultation $consultation): bool
    {
        // Jamais de suppression physique (spec §3) — même périmètre que la
        // mise à jour pour la suppression logique côté service.
        return $this->update($actor, $consultation);
    }
}
