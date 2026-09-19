<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\HealthManager\Domain\Access\HealthAccess;
use App\Modules\HealthManager\Domain\Models\HealthAppointment;

/**
 * HC-004 (#7788) — Policy des rendez-vous (BC-30), deny-by-default.
 *
 * Direction (`health.admin`) et accueil (`health.reception`) planifient et
 * voient TOUT ; un praticien actif ne voit que SON agenda (les rendez-vous
 * dont il est le praticien) et peut faire avancer le statut de SES
 * rendez-vous (arrivée, terminé). Employé lambda : 403 partout.
 */
class HealthAppointmentPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return HealthAccess::canViewAppointments($actor);
    }

    public function view(Employee $actor, HealthAppointment $appointment): bool
    {
        if ($appointment->company_id !== $actor->company_id) {
            return false;
        }

        if (HealthAccess::canManageAppointments($actor)) {
            return true;
        }

        // Praticien : uniquement SON agenda.
        return HealthAccess::practitionerId($actor) === $appointment->practitioner_id;
    }

    public function create(Employee $actor): bool
    {
        return HealthAccess::canManageAppointments($actor);
    }

    public function update(Employee $actor, HealthAppointment $appointment): bool
    {
        return $this->create($actor) && $appointment->company_id === $actor->company_id;
    }

    public function delete(Employee $actor, HealthAppointment $appointment): bool
    {
        return $this->update($actor, $appointment);
    }

    /**
     * Transition de statut : gestionnaires, ou le praticien du rendez-vous
     * lui-même (arrivée du patient, consultation terminée).
     */
    public function transition(Employee $actor, HealthAppointment $appointment): bool
    {
        return $this->view($actor, $appointment);
    }
}
