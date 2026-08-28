<?php

declare(strict_types=1);

namespace App\Policies\Crm;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\CRM\Domain\Models\CrmLead;

/**
 * Policy d'accès aux leads CRM client — Issue #5711 (CRM-V0-07).
 *
 * RBAC tenant CRM :
 *   - `principal` / `rh` : accès complet (voir/muter) sur les leads du tenant ;
 *   - le propriétaire (`owner_id`) conserve l'accès en lecture/édition sur son
 *     lead (travail délégué) ;
 *   - suppression réservée aux managers du tenant (aucune suppression
 *     « propriétaire seul »).
 * Les employés non-managers n'atteignent jamais ces routes (middleware
 * `api.manager:principal,rh` → 403) ; la Policy reste néanmoins la garde
 * applicative unique (aucune garde inline dans les contrôleurs).
 *
 * Isolation tenant : le scope global `BelongsToCompany` rend les leads des
 * autres tenants introuvables (404) avant même la Policy.
 */
class CrmLeadPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return $this->isCrmManager($actor);
    }

    public function view(Employee $actor, CrmLead $lead): bool
    {
        return $this->isCrmManager($actor)
            || $lead->owner_id === $actor->id;
    }

    public function create(Employee $actor): bool
    {
        return $this->isCrmManager($actor);
    }

    public function update(Employee $actor, CrmLead $lead): bool
    {
        return $this->isCrmManager($actor)
            || $lead->owner_id === $actor->id;
    }

    public function delete(Employee $actor, CrmLead $lead): bool
    {
        return $this->isCrmManager($actor);
    }

    private function isCrmManager(Employee $actor): bool
    {
        return $actor->isManager() && in_array($actor->manager_role, ['principal', 'rh'], true);
    }
}
