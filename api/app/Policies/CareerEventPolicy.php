<?php

namespace App\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\HR\Domain\Models\CareerEvent;

/**
 * Politique des événements de carrière (plans de carrière, issue #5259).
 *
 * RBAC aligné sur EvaluationPolicy (PA2-SEC-002/003) :
 *  - Manager : crée/édite/valide/applique/supprime tant que l'événement est
 *    dans un état modifiable ; manager_role=dept restreint à son département,
 *    superviseur à son équipe directe.
 *  - Employé : lecture seule de ses propres événements.
 *  - Isolation tenant : toute action cross-tenant → false.
 */
class CareerEventPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return true; // Le filtrage est fait dans le controller index
    }

    public function view(Employee $actor, CareerEvent $event): bool
    {
        if ((string) $event->company_id !== (string) $actor->company_id) {
            return false;
        }

        if ($actor->id === $event->employee_id) {
            return true;
        }

        return $this->managesCareerEmployee($actor, $event);
    }

    public function create(Employee $actor): bool
    {
        return $actor->isManager();
    }

    public function update(Employee $actor, CareerEvent $event): bool
    {
        if ((string) $event->company_id !== (string) $actor->company_id) {
            return false;
        }

        if ($event->status !== 'pending') {
            return false;
        }

        return $this->managesCareerEmployee($actor, $event);
    }

    public function approve(Employee $actor, CareerEvent $event): bool
    {
        if ((string) $event->company_id !== (string) $actor->company_id) {
            return false;
        }

        if ($event->status !== 'pending') {
            return false;
        }

        return $this->managesCareerEmployee($actor, $event);
    }

    public function reject(Employee $actor, CareerEvent $event): bool
    {
        if ((string) $event->company_id !== (string) $actor->company_id) {
            return false;
        }

        if ($event->status !== 'pending') {
            return false;
        }

        return $this->managesCareerEmployee($actor, $event);
    }

    public function apply(Employee $actor, CareerEvent $event): bool
    {
        if ((string) $event->company_id !== (string) $actor->company_id) {
            return false;
        }

        if ($event->status !== 'approved') {
            return false;
        }

        return $this->managesCareerEmployee($actor, $event);
    }

    public function delete(Employee $actor, CareerEvent $event): bool
    {
        if ((string) $event->company_id !== (string) $actor->company_id) {
            return false;
        }

        if ($event->status !== 'pending') {
            return false;
        }

        return $this->managesCareerEmployee($actor, $event);
    }

    /**
     * PA2-SEC-002 / PA2-SEC-003 : manager_role=dept ne peut agir que sur les
     * employés de son propre département ; superviseur uniquement sur son
     * équipe directe. Les rôles manager tenant-wide ne sont pas restreints.
     */
    private function managesCareerEmployee(Employee $actor, CareerEvent $event): bool
    {
        if (! $actor->isManager()) {
            return false;
        }

        if (! $actor->isTeamScoped()) {
            return true;
        }

        $target = $event->employee;

        return $target !== null && $actor->managesTeamMemberOf($target);
    }
}
