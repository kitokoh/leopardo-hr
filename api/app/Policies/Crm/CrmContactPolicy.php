<?php

declare(strict_types=1);

namespace App\Policies\Crm;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\CRM\Domain\Models\CrmContact;

/**
 * Policy d'accès aux contacts CRM client — Issue #5711 (CRM-V0-07).
 *
 * Même contrat que `CrmLeadPolicy` : managers `principal`/`rh` (accès
 * complet) + propriétaire du contact ou de son compte (`owner_id` ou
 * `account.owner_id`) en lecture/édition. Suppression réservée aux
 * managers. Isolation tenant via `BelongsToCompany` (404 cross-tenant).
 */
class CrmContactPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return $this->isCrmManager($actor);
    }

    public function view(Employee $actor, CrmContact $contact): bool
    {
        return $this->isCrmManager($actor)
            || $this->ownsContact($actor, $contact);
    }

    public function create(Employee $actor): bool
    {
        return $this->isCrmManager($actor);
    }

    public function update(Employee $actor, CrmContact $contact): bool
    {
        return $this->isCrmManager($actor)
            || $this->ownsContact($actor, $contact);
    }

    public function delete(Employee $actor, CrmContact $contact): bool
    {
        return $this->isCrmManager($actor);
    }

    private function isCrmManager(Employee $actor): bool
    {
        return $actor->isManager() && in_array($actor->manager_role, ['principal', 'rh'], true);
    }

    private function ownsContact(Employee $actor, CrmContact $contact): bool
    {
        if ($contact->account?->owner_id === $actor->id) {
            return true;
        }

        return false;
    }
}
