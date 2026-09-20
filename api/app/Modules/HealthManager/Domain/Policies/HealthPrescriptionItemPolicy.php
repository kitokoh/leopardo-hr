<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\HealthManager\Domain\Access\HealthAccess;
use App\Modules\HealthManager\Domain\Models\HealthPrescriptionItem;

/**
 * #7789 (BC-31) — Policy des lignes d'ordonnance (contenu MÉDICAL).
 *
 * Deny-by-default (spec §2) : même périmètre que l'ordonnance parente —
 * praticiens + direction en lecture ; auteur (praticien de l'ordonnance)
 * ou direction en écriture ; réception et facturation TOUJOURS refusées.
 * Cross-tenant → refus (fail-closed).
 */
class HealthPrescriptionItemPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return HealthAccess::isAdmin($actor) || HealthAccess::isPractitioner($actor);
    }

    public function view(Employee $actor, HealthPrescriptionItem $item): bool
    {
        return $item->company_id === $actor->company_id && $this->viewAny($actor);
    }

    public function create(Employee $actor): bool
    {
        return HealthAccess::isAdmin($actor) || HealthAccess::isPractitioner($actor);
    }

    public function update(Employee $actor, HealthPrescriptionItem $item): bool
    {
        if ($item->company_id !== $actor->company_id) {
            return false;
        }

        if (HealthAccess::isAdmin($actor)) {
            return true;
        }

        $practitionerId = HealthAccess::practitionerId($actor);

        if ($practitionerId === null) {
            return false;
        }

        $prescription = $item->prescription;

        return $prescription !== null
            && $prescription->company_id === $actor->company_id
            && $prescription->practitioner_id === $practitionerId;
    }

    public function delete(Employee $actor, HealthPrescriptionItem $item): bool
    {
        return $this->update($actor, $item);
    }
}
