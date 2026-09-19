<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\HealthManager\Domain\Access\HealthAccess;
use App\Modules\HealthManager\Domain\Models\HealthAppointment;

/**
 * #7788 (BC-30) — Policy des rendez-vous.
 *
 * Deny-by-default (spec §2) : direction et réception gèrent TOUS les
 * rendez-vous ; un praticien voit et met à jour UNIQUEMENT les siens
 * (practitioner_id = sa fiche active) ; facturation et employé lambda
 * refusés. Cross-tenant → refus (fail-closed).
 */
class HealthAppointmentPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return $this->manages($actor) || HealthAccess::isPractitioner($actor);
    }

    public function view(Employee $actor, HealthAppointment $appointment): bool
    {
        if ($appointment->company_id !== $actor->company_id) {
            return false;
        }

        return $this->manages($actor) || $this->owns($actor, $appointment);
    }

    public function create(Employee $actor): bool
    {
        return $this->manages($actor);
    }

    public function update(Employee $actor, HealthAppointment $appointment): bool
    {
        if ($appointment->company_id !== $actor->company_id) {
            return false;
        }

        return $this->manages($actor) || $this->owns($actor, $appointment);
    }

    public function delete(Employee $actor, HealthAppointment $appointment): bool
    {
        return $appointment->company_id === $actor->company_id && $this->manages($actor);
    }

    private function manages(Employee $actor): bool
    {
        return HealthAccess::isAdmin($actor) || HealthAccess::isReception($actor);
    }

    private function owns(Employee $actor, HealthAppointment $appointment): bool
    {
        $practitionerId = HealthAccess::practitionerId($actor);

        return $practitionerId !== null && $appointment->practitioner_id === $practitionerId;
    }
}
