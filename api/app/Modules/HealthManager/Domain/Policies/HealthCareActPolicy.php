<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\HealthManager\Domain\Access\HealthAccess;
use App\Modules\HealthManager\Domain\Models\HealthCareAct;

/**
 * #7791 (BC-31) — Policy du catalogue d'actes de soins.
 *
 * Deny-by-default (spec §2) : direction et facturation UNIQUEMENT —
 * réception, praticiens et employé lambda refusés.
 * Cross-tenant → refus (fail-closed).
 */
class HealthCareActPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return HealthAccess::isAdmin($actor) || HealthAccess::isBilling($actor);
    }

    public function view(Employee $actor, HealthCareAct $careAct): bool
    {
        return $careAct->company_id === $actor->company_id && $this->viewAny($actor);
    }

    public function create(Employee $actor): bool
    {
        return HealthAccess::isAdmin($actor) || HealthAccess::isBilling($actor);
    }

    public function update(Employee $actor, HealthCareAct $careAct): bool
    {
        return $careAct->company_id === $actor->company_id
            && (HealthAccess::isAdmin($actor) || HealthAccess::isBilling($actor));
    }

    public function delete(Employee $actor, HealthCareAct $careAct): bool
    {
        return $this->update($actor, $careAct);
    }
}
