<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\HealthManager\Domain\Access\HealthAccess;
use App\Modules\HealthManager\Domain\Models\HealthPrescription;

/**
 * #7789 (BC-30) — Policy des ordonnances (contenu MÉDICAL).
 *
 * Deny-by-default (spec §2) : contenu médical visible des praticiens et de
 * la direction UNIQUEMENT — réception et facturation TOUJOURS refusées.
 * Création par un praticien ; mise à jour par l'AUTEUR (sa fiche
 * praticien) ou la direction. Cross-tenant → refus (fail-closed).
 */
class HealthPrescriptionPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return HealthAccess::isAdmin($actor) || HealthAccess::isPractitioner($actor);
    }

    public function view(Employee $actor, HealthPrescription $prescription): bool
    {
        return $prescription->company_id === $actor->company_id && $this->viewAny($actor);
    }

    public function create(Employee $actor): bool
    {
        return HealthAccess::isAdmin($actor) || HealthAccess::isPractitioner($actor);
    }

    public function update(Employee $actor, HealthPrescription $prescription): bool
    {
        if ($prescription->company_id !== $actor->company_id) {
            return false;
        }

        if (HealthAccess::isAdmin($actor)) {
            return true;
        }

        $practitionerId = HealthAccess::practitionerId($actor);

        return $practitionerId !== null && $prescription->practitioner_id === $practitionerId;
    }

    public function delete(Employee $actor, HealthPrescription $prescription): bool
    {
        return $this->update($actor, $prescription);
    }
}
