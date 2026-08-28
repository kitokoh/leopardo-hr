<?php

declare(strict_types=1);

namespace App\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\CRM\Domain\Models\CrmTask;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Issue #5720 — Politique d'accès aux tâches CRM client.
 *
 * Rôles commerciaux tenant (principal/rh/marketing) : gestion complète des
 * tâches du tenant. L'assigné d'une tâche (employé ou manager) peut voir et
 * faire évoluer SA tâche. Cross-tenant : fail-closed (company_id différent →
 * refus).
 */
class CrmTaskPolicy
{
    use HandlesAuthorization;

    private function isCrmRole(Employee $actor): bool
    {
        return $actor->isManager() && $actor->hasManagerRole('principal', 'rh', 'marketing');
    }

    public function viewAny(Employee $actor): bool
    {
        return $this->isCrmRole($actor);
    }

    public function viewTimeline(Employee $actor): bool
    {
        return $this->isCrmRole($actor);
    }

    public function view(Employee $actor, CrmTask $task): bool
    {
        if ($task->company_id !== $actor->company_id) {
            return false;
        }

        return $this->isCrmRole($actor) || $task->assignee_id === $actor->id;
    }

    public function create(Employee $actor): bool
    {
        return $this->isCrmRole($actor);
    }

    public function update(Employee $actor, CrmTask $task): bool
    {
        if ($task->company_id !== $actor->company_id) {
            return false;
        }

        return $this->isCrmRole($actor) || $task->assignee_id === $actor->id;
    }

    public function complete(Employee $actor, CrmTask $task): bool
    {
        return $this->update($actor, $task);
    }

    public function reopen(Employee $actor, CrmTask $task): bool
    {
        return $this->update($actor, $task);
    }

    public function delete(Employee $actor, CrmTask $task): bool
    {
        if ($task->company_id !== $actor->company_id) {
            return false;
        }

        return $this->isCrmRole($actor);
    }
}
