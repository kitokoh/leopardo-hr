<?php

declare(strict_types=1);

namespace App\Policies\Crm;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\CRM\Domain\Models\CrmActivity;

/**
 * Policy d'accès à la timeline CRM (activities, append-only) — Issue #5711
 * (CRM-V0-07).
 *
 * La timeline est append-only : aucune route de mise à jour (pas de
 * `update`), la création est réservée aux managers `principal`/`rh` du
 * tenant, la suppression (correction d'erreur) aux mêmes managers. Le
 * propriétaire d'une activité peut la lire.
 */
class CrmActivityPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return $this->isCrmManager($actor);
    }

    public function view(Employee $actor, CrmActivity $activity): bool
    {
        return $this->isCrmManager($actor)
            || $activity->owner_id === $actor->id;
    }

    public function create(Employee $actor): bool
    {
        return $this->isCrmManager($actor);
    }

    public function delete(Employee $actor, CrmActivity $activity): bool
    {
        return $this->isCrmManager($actor);
    }

    private function isCrmManager(Employee $actor): bool
    {
        return $actor->isManager() && in_array($actor->manager_role, ['principal', 'rh'], true);
    }
}
