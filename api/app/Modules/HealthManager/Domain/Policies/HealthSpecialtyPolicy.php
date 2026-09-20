<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\HealthManager\Domain\Access\HealthAccess;
use App\Modules\HealthManager\Domain\Models\HealthSpecialty;

/**
 * #7786 (BC-31) — Policy des spécialités médicales (référentiel).
 *
 * Deny-by-default (spec §2) : gestion réservée à la direction
 * (`health.admin`) ; lecture ouverte aux praticiens et à la réception.
 * Cross-tenant → refus (fail-closed).
 */
class HealthSpecialtyPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return HealthAccess::isAdmin($actor)
            || HealthAccess::isPractitioner($actor)
            || HealthAccess::isReception($actor);
    }

    public function view(Employee $actor, HealthSpecialty $specialty): bool
    {
        return $specialty->company_id === $actor->company_id && $this->viewAny($actor);
    }

    public function create(Employee $actor): bool
    {
        return HealthAccess::isAdmin($actor);
    }

    public function update(Employee $actor, HealthSpecialty $specialty): bool
    {
        return $specialty->company_id === $actor->company_id && HealthAccess::isAdmin($actor);
    }

    public function delete(Employee $actor, HealthSpecialty $specialty): bool
    {
        return $this->update($actor, $specialty);
    }
}
