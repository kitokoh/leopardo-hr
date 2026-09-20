<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\HealthManager\Domain\Access\HealthAccess;
use App\Modules\HealthManager\Domain\Models\HealthStaffRole;

/**
 * #7786 (BC-31) — Policy des rôles opérationnels (réception, facturation).
 *
 * Deny-by-default (spec §2) : gestion réservée à la direction
 * (`health.admin`) ; lecture ouverte aux praticiens et à la réception.
 * Cross-tenant → refus (fail-closed).
 */
class HealthStaffRolePolicy
{
    public function viewAny(Employee $actor): bool
    {
        return HealthAccess::isAdmin($actor)
            || HealthAccess::isPractitioner($actor)
            || HealthAccess::isReception($actor);
    }

    public function view(Employee $actor, HealthStaffRole $staffRole): bool
    {
        return $staffRole->company_id === $actor->company_id && $this->viewAny($actor);
    }

    public function create(Employee $actor): bool
    {
        return HealthAccess::isAdmin($actor);
    }

    public function update(Employee $actor, HealthStaffRole $staffRole): bool
    {
        return $staffRole->company_id === $actor->company_id && HealthAccess::isAdmin($actor);
    }

    public function delete(Employee $actor, HealthStaffRole $staffRole): bool
    {
        return $this->update($actor, $staffRole);
    }
}
