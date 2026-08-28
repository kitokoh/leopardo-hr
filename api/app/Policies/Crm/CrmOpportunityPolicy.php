<?php

declare(strict_types=1);

namespace App\Policies\Crm;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\CRM\Domain\Models\CrmOpportunity;

/**
 * Policy d'accès aux opportunités CRM client — Issue #5711 (CRM-V0-07).
 *
 * Même contrat que `CrmLeadPolicy` : managers `principal`/`rh` du tenant
 * (accès complet) + propriétaire (`owner_id`) en lecture/édition.
 * Suppression réservée aux managers. Isolation tenant via le scope
 * `BelongsToCompany` (404 cross-tenant avant la Policy).
 */
class CrmOpportunityPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return $this->isCrmManager($actor);
    }

    public function view(Employee $actor, CrmOpportunity $opportunity): bool
    {
        return $this->isCrmManager($actor)
            || $opportunity->owner_id === $actor->id;
    }

    public function create(Employee $actor): bool
    {
        return $this->isCrmManager($actor);
    }

    public function update(Employee $actor, CrmOpportunity $opportunity): bool
    {
        return $this->isCrmManager($actor)
            || $opportunity->owner_id === $actor->id;
    }

    public function delete(Employee $actor, CrmOpportunity $opportunity): bool
    {
        return $this->isCrmManager($actor);
    }

    private function isCrmManager(Employee $actor): bool
    {
        return $actor->isManager() && in_array($actor->manager_role, ['principal', 'rh'], true);
    }
}
