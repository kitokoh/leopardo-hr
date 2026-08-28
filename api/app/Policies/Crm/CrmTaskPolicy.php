<?php

declare(strict_types=1);

namespace App\Policies\Crm;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\CRM\Domain\Models\CrmTask;

/**
 * Policy d'accès aux tâches CRM client — Issue #5711 (CRM-V0-07).
 *
 * Managers `principal`/`rh` : accès complet. Assigné (`assignee_id`) :
 * lecture/édition de sa tâche (travail délégué). Suppression réservée aux
 * managers. Isolation tenant via le scope `BelongsToCompany` (404
 * cross-tenant avant la Policy).
 */
class CrmTaskPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return $this->isCrmManager($actor);
    }

    public function view(Employee $actor, CrmTask $task): bool
    {
        return $this->isCrmManager($actor)
            || $task->assignee_id === $actor->id;
    }

    public function create(Employee $actor): bool
    {
        return $this->isCrmManager($actor);
    }

    public function update(Employee $actor, CrmTask $task): bool
    {
        return $this->isCrmManager($actor)
            || $task->assignee_id === $actor->id;
    }

    public function delete(Employee $actor, CrmTask $task): bool
    {
        return $this->isCrmManager($actor);
    }

    private function isCrmManager(Employee $actor): bool
    {
        return $actor->isManager() && in_array($actor->manager_role, ['principal', 'rh'], true);
    }
}
